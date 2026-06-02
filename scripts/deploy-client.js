/**
 * Despliegue local de tema y plugins WordPress por cliente.
 *
 * Uso:
 *   node scripts/deploy-client.js <hostname> [--env production|staging] [--dry-run]
 *
 * Config (no commitear credenciales):
 *   front/<categoria>/<hostname>/deploy.config.json
 *   front/<categoria>/<hostname>/deploy.<env>.json  (opcional, pisa valores base)
 *
 * Variables de entorno opcionales:
 *   DEPLOY_PASSWORD — contraseña SFTP/FTP si no está en el JSON
 */

import { readFileSync, existsSync, statSync, readdirSync } from 'node:fs';
import { resolve, join, relative, posix } from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = resolve(fileURLToPath(new URL('.', import.meta.url)), '..');

const DEFAULT_EXCLUDE = [
	'node_modules',
	'.git',
	'tests',
	'test',
	'vendor',
	'.env',
	'.env.*',
	'*.map',
	'.DS_Store',
	'Thumbs.db',
];

/**
 * @param {string[]} argv
 */
function parseArgs(argv) {
	const positional = [];
	let env = 'production';
	let dryRun = false;
	let only = '';

	for (let i = 0; i < argv.length; i++) {
		const arg = argv[i];
		if (arg === '--env' && i + 1 < argv.length) {
			env = argv[++i];
			continue;
		}
		if (arg === '--only' && i + 1 < argv.length) {
			only = argv[++i];
			continue;
		}
		if (arg === '--dry-run') {
			dryRun = true;
			continue;
		}
		if (arg === '--help' || arg === '-h') {
			printHelp();
			process.exit(0);
		}
		if (!arg.startsWith('-')) {
			positional.push(arg);
		}
	}

	return { hostname: positional[0] || '', env, dryRun, only };
}

function printHelp() {
	console.log(`Despliegue local — tema y plugins por cliente

Uso:
  npm run deploy -- <hostname> [--env production|staging] [--dry-run]

Ejemplo:
  npm run deploy -- mi-cliente.local
  npm run deploy -- mi-cliente.local --env staging --dry-run
  npm run deploy -- mi-cliente.local --only woocommerce-erp-integration

Configuración (por cliente, en gitignore):
  front/ecommerce/<hostname>/deploy.config.json
  front/ecommerce/<hostname>/deploy.<env>.json

Copia deploy.config.example.json → deploy.config.json y completa host, rutas y credenciales.
`);
}

/**
 * @param {string} hostname
 */
function resolveClientFromRegistry(hostname) {
	const registryPath = join(repoRoot, 'clients.json');
	if (!existsSync(registryPath)) {
		return null;
	}
	const data = JSON.parse(readFileSync(registryPath, 'utf-8'));
	const client = (data.clients || []).find((c) => c.hostname === hostname);
	if (!client?.frontPath) {
		return null;
	}
	return {
		hostname,
		clientRoot: resolve(repoRoot, client.frontPath),
		category: client.category || 'ecommerce',
	};
}

/**
 * @param {string} hostname
 */
function resolveClientByScan(hostname) {
	const frontDir = join(repoRoot, 'front');
	if (!existsSync(frontDir)) {
		return null;
	}

	for (const category of ['ecommerce', 'portafolio', 'landing-page']) {
		const candidate = join(frontDir, category, hostname);
		if (existsSync(candidate) && statSync(candidate).isDirectory()) {
			return {
				hostname,
				clientRoot: candidate,
				category,
			};
		}
	}

	return null;
}

/**
 * @param {string} clientRoot
 * @param {string} env
 */
function loadDeployConfig(clientRoot, env) {
	const basePath = join(clientRoot, 'deploy.config.json');
	const envPath = join(clientRoot, `deploy.${env}.json`);

	if (!existsSync(basePath) && !existsSync(envPath)) {
		throw new Error(
			`No se encontró deploy.config.json en ${clientRoot}\n` +
				`Copia deploy.config.example.json → deploy.config.json y configura FTP/SFTP.`
		);
	}

	/** @type {Record<string, unknown>} */
	let config = {};
	if (existsSync(basePath)) {
		config = JSON.parse(readFileSync(basePath, 'utf-8'));
	}
	if (existsSync(envPath)) {
		const envConfig = JSON.parse(readFileSync(envPath, 'utf-8'));
		config = { ...config, ...envConfig };
	}

	return config;
}

/**
 * @param {Record<string, unknown>} config
 * @param {{ hostname: string, clientRoot: string }} client
 */
function validateConfig(config, client) {
	const required = ['protocol', 'host', 'remoteThemesPath', 'remotePluginsPath'];
	for (const key of required) {
		if (!config[key] || typeof config[key] !== 'string') {
			throw new Error(`Falta o es inválido el campo "${key}" en deploy.config.json`);
		}
	}

	const protocol = String(config.protocol).toLowerCase();
	if (protocol !== 'sftp' && protocol !== 'ftp') {
		throw new Error('protocol debe ser "sftp" o "ftp"');
	}

	const port = Number(config.port) || (protocol === 'sftp' ? 22 : 21);
	if (protocol === 'sftp' && port === 21) {
		throw new Error(
			'protocol "sftp" con puerto 21 no es válido. FileZilla en puerto 21 usa FTP (no SFTP). Cambia a "protocol": "ftp" y "secure": true para TLS explícito.'
		);
	}
	if (protocol === 'ftp' && port === 22) {
		console.warn('⚠ protocol ftp con puerto 22 es inusual; ¿querías sftp?');
	}

	if (!config.username && !config.privateKeyPath) {
		throw new Error('Indica username y password (o DEPLOY_PASSWORD) o privateKeyPath para SFTP');
	}

	const wpContent = join(client.clientRoot, 'wp-content');
	if (!existsSync(wpContent)) {
		throw new Error(`No existe wp-content en ${client.clientRoot}`);
	}

	return {
		protocol,
		host: String(config.host),
		port,
		username: config.username ? String(config.username) : '',
		password: config.password
			? String(config.password)
			: process.env.DEPLOY_PASSWORD || '',
		privateKeyPath: config.privateKeyPath ? String(config.privateKeyPath) : '',
		passive: config.passive !== false,
		secure: resolveFtpSecure(config),
		remoteThemesPath: normalizeRemotePath(String(config.remoteThemesPath)),
		remotePluginsPath: normalizeRemotePath(String(config.remotePluginsPath)),
		themes: Array.isArray(config.themes) ? config.themes.map(String) : [],
		plugins: Array.isArray(config.plugins) ? config.plugins.map(String) : [],
		exclude: Array.isArray(config.exclude)
			? [...DEFAULT_EXCLUDE, ...config.exclude.map(String)]
			: DEFAULT_EXCLUDE,
	};
}

/**
 * @param {string} p
 */
function normalizeRemotePath(p) {
	return p.replace(/\\/g, '/').replace(/\/+$/, '');
}

/**
 * FTPS explícito (FileZilla: "FTP sobre TLS") → true. Implícito (puerto 990) → "implicit".
 *
 * @param {Record<string, unknown>} config
 * @returns {boolean | 'implicit'}
 */
function resolveFtpSecure(config) {
	if (config.secure === 'implicit') {
		return 'implicit';
	}
	if (config.secure === false) {
		return false;
	}
	if (config.secure === true || config.secure === 'explicit') {
		return true;
	}
	// Sin "secure" en JSON: puerto 21 suele ser FTP+TLS explícito en hosting compartido.
	const port = Number(config.port) || 21;
	return port === 21;
}

/**
 * @param {string} itemPath
 * @param {string[]} exclude
 */
function shouldExclude(itemPath, exclude) {
	const name = itemPath.split(/[/\\]/).pop() || '';
	for (const pattern of exclude) {
		if (pattern.includes('*')) {
			const re = new RegExp(
				'^' + pattern.replace(/\./g, '\\.').replace(/\*/g, '.*') + '$'
			);
			if (re.test(name)) {
				return true;
			}
		} else if (name === pattern || itemPath.includes(`/${pattern}/`) || itemPath.includes(`\\${pattern}\\`)) {
			return true;
		}
	}
	return false;
}

/**
 * @param {string} dir
 * @param {string[]} exclude
 * @returns {string[]}
 */
function listFilesRecursive(dir, exclude) {
	/** @type {string[]} */
	const files = [];
	const stack = [dir];

	while (stack.length) {
		const current = stack.pop();
		const entries = readdirSync(current, { withFileTypes: true });
		for (const entry of entries) {
			const full = join(current, entry.name);
			if (shouldExclude(full, exclude)) {
				continue;
			}
			if (entry.isDirectory()) {
				stack.push(full);
			} else if (entry.isFile()) {
				files.push(full);
			}
		}
	}
	return files;
}

/**
 * @param {{ hostname: string, clientRoot: string }} client
 * @param {ReturnType<typeof validateConfig>} cfg
 */
function buildUploadPlan(client, cfg) {
	const wpContent = join(client.clientRoot, 'wp-content');
	/** @type {{ label: string, localDir: string, remoteDir: string }[]} */
	const plan = [];

	const themesRoot = join(wpContent, 'themes');
	if (existsSync(themesRoot)) {
		const themeNames =
			cfg.themes.length > 0
				? cfg.themes
				: readdirSync(themesRoot, { withFileTypes: true })
						.filter((d) => d.isDirectory())
						.map((d) => d.name);

		for (const theme of themeNames) {
			const localDir = join(themesRoot, theme);
			if (!existsSync(localDir)) {
				throw new Error(`Tema no encontrado: ${localDir}`);
			}
			plan.push({
				label: `theme:${theme}`,
				localDir,
				remoteDir: `${cfg.remoteThemesPath}/${theme}`,
			});
		}
	}

	const pluginsRoot = join(wpContent, 'plugins');
	if (existsSync(pluginsRoot)) {
		const pluginNames =
			cfg.plugins.length > 0
				? cfg.plugins
				: readdirSync(pluginsRoot, { withFileTypes: true })
						.filter((d) => d.isDirectory())
						.map((d) => d.name);

		for (const plugin of pluginNames) {
			const localDir = join(pluginsRoot, plugin);
			if (!existsSync(localDir)) {
				throw new Error(`Plugin no encontrado: ${localDir}`);
			}
			plan.push({
				label: `plugin:${plugin}`,
				localDir,
				remoteDir: `${cfg.remotePluginsPath}/${plugin}`,
			});
		}
	}

	if (!plan.length) {
		throw new Error('No hay temas ni plugins para desplegar. Revisa themes/plugins en deploy.config.json');
	}

	return plan;
}

/**
 * @param {ReturnType<typeof validateConfig>} cfg
 * @param {{ label: string, localDir: string, remoteDir: string }[]} plan
 * @param {boolean} dryRun
 */
async function deploySftp(cfg, plan, dryRun) {
	const SftpClient = (await import('ssh2-sftp-client')).default;
	const sftp = new SftpClient();

	/** @type {Record<string, unknown>} */
	const connectOpts = {
		host: cfg.host,
		port: cfg.port,
		username: cfg.username,
	};

	if (cfg.privateKeyPath) {
		const keyPath = resolve(cfg.privateKeyPath);
		connectOpts.privateKey = readFileSync(keyPath, 'utf-8');
	} else if (cfg.password) {
		connectOpts.password = cfg.password;
	} else {
		throw new Error('SFTP requiere password (DEPLOY_PASSWORD) o privateKeyPath en deploy.config.json');
	}

	if (!dryRun) {
		await sftp.connect(connectOpts);
	}

	try {
		for (const item of plan) {
			const files = listFilesRecursive(item.localDir, cfg.exclude);
			console.log(`\n→ ${item.label}: ${files.length} archivo(s)`);
			console.log(`  local:  ${item.localDir}`);
			console.log(`  remoto: ${item.remoteDir}`);

			if (dryRun) {
				for (const file of files.slice(0, 5)) {
					const rel = relative(item.localDir, file);
					console.log(`  [dry-run] ${posix.join(item.remoteDir, rel.replace(/\\/g, '/'))}`);
				}
				if (files.length > 5) {
					console.log(`  … y ${files.length - 5} más`);
				}
				continue;
			}

			await sftp.mkdir(item.remoteDir, true);
			await sftp.uploadDir(item.localDir, item.remoteDir, {
				filter: (localPath) => {
					const rel = relative(item.localDir, localPath);
					if (!rel || rel === '.') {
						return true;
					}
					return !shouldExclude(localPath, cfg.exclude);
				},
			});
			console.log(`  ✓ Subido`);
		}
	} finally {
		if (!dryRun) {
			await sftp.end();
		}
	}
}

/**
 * @param {unknown} err
 */
function isFtpRetryableError(err) {
	const msg = err instanceof Error ? err.message : String(err);
	return /ECONNRESET|ETIMEDOUT|ECONNREFUSED|EPIPE|timeout|control socket|421|426/i.test(
		msg
	);
}

/**
 * @param {number} ms
 */
function sleep(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * @param {ReturnType<typeof validateConfig>} cfg
 */
async function createFtpClient(cfg) {
	const { Client } = await import('basic-ftp');
	const client = new Client(180000);
	client.ftp.verbose = false;
	await client.access({
		host: cfg.host,
		port: cfg.port,
		user: cfg.username,
		password: cfg.password,
		secure: cfg.secure,
		secureOptions: cfg.secure ? { rejectUnauthorized: false } : undefined,
	});
	return client;
}

/**
 * @param {() => Promise<void>} fn
 * @param {number} maxAttempts
 */
async function withFtpRetries(fn, maxAttempts = 3) {
	let lastError;
	for (let attempt = 1; attempt <= maxAttempts; attempt++) {
		try {
			await fn();
			return;
		} catch (err) {
			lastError = err;
			if (attempt >= maxAttempts || !isFtpRetryableError(err)) {
				throw err;
			}
			const msg = err instanceof Error ? err.message : String(err);
			console.warn(`  Conexión interrumpida (${msg}). Reintento ${attempt + 1}/${maxAttempts}…`);
			await sleep(2500 * attempt);
		}
	}
	throw lastError;
}

/**
 * @param {ReturnType<typeof validateConfig>} cfg
 * @param {{ label: string, localDir: string, remoteDir: string }} item
 */
async function uploadFtpItem(cfg, item) {
	await withFtpRetries(async () => {
		const client = await createFtpClient(cfg);
		try {
			await client.ensureDir(item.remoteDir);
			await client.uploadFromDir(item.localDir, item.remoteDir, {
				filter: (localPath) => !shouldExclude(localPath, cfg.exclude),
			});
		} finally {
			client.close();
		}
	});
}

/**
 * @param {ReturnType<typeof validateConfig>} cfg
 * @param {{ label: string, localDir: string, remoteDir: string }[]} plan
 * @param {boolean} dryRun
 */
async function deployFtp(cfg, plan, dryRun) {
	for (const item of plan) {
		const files = listFilesRecursive(item.localDir, cfg.exclude);
		console.log(`\n→ ${item.label}: ${files.length} archivo(s)`);
		console.log(`  local:  ${item.localDir}`);
		console.log(`  remoto: ${item.remoteDir}`);

		if (dryRun) {
			for (const file of files.slice(0, 5)) {
				const rel = relative(item.localDir, file).replace(/\\/g, '/');
				console.log(`  [dry-run] ${posix.join(item.remoteDir, rel)}`);
			}
			if (files.length > 5) {
				console.log(`  … y ${files.length - 5} más`);
			}
			continue;
		}

		await uploadFtpItem(cfg, item);
		console.log(`  ✓ Subido`);
	}
}

async function main() {
	const { hostname, env, dryRun, only } = parseArgs(process.argv.slice(2));

	if (!hostname) {
		printHelp();
		process.exit(1);
	}

	const client =
		resolveClientFromRegistry(hostname) || resolveClientByScan(hostname);
	if (!client) {
		console.error(`✗ Cliente no encontrado: ${hostname}`);
		console.error('  Regístralo en clients.json o crea front/ecommerce/<hostname>/');
		process.exit(1);
	}

	console.log(`Cliente: ${client.hostname}`);
	console.log(`Ruta:    ${client.clientRoot}`);
	console.log(`Entorno: ${env}${dryRun ? ' (dry-run)' : ''}`);

	const rawConfig = loadDeployConfig(client.clientRoot, env);
	const cfg = validateConfig(rawConfig, client);
	let plan = buildUploadPlan(client, cfg);
	if (only) {
		const needle = only.toLowerCase();
		plan = plan.filter(
			(p) =>
				p.label.toLowerCase().includes(needle) ||
				p.localDir.toLowerCase().includes(needle)
		);
		if (!plan.length) {
			throw new Error(`Ningún tema/plugin coincide con --only ${only}`);
		}
		console.log(`Filtro --only: ${plan.map((p) => p.label).join(', ')}`);
	}

	console.log(`Protocolo: ${cfg.protocol}://${cfg.host}:${cfg.port}`);

	if (cfg.protocol === 'sftp') {
		await deploySftp(cfg, plan, dryRun);
	} else {
		if (!cfg.password) {
			throw new Error('FTP requiere password en deploy.config.json o DEPLOY_PASSWORD');
		}
		await deployFtp(cfg, plan, dryRun);
	}

	console.log(dryRun ? '\n✓ Dry-run completado (no se subió nada).' : '\n✓ Despliegue completado.');
}

main().catch((err) => {
	console.error(`\n✗ ${err.message}`);
	process.exit(1);
});
