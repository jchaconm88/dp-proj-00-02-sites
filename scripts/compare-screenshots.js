/**
 * Visual comparison tool for design screenshots vs implementation screenshots.
 * Compares a design screenshot (screen.png) with an implementation screenshot
 * using pixel-level comparison via pixelmatch, generating a diff image that
 * highlights differences.
 *
 * @module compare-screenshots
 */

import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

/**
 * Compares two PNG screenshots and generates a diff image.
 * @param {object} options
 * @param {string} options.designScreenshot - Path to the design screenshot (screen.png)
 * @param {string} options.implementationScreenshot - Path to the implementation screenshot
 * @param {number} [options.threshold=0.05] - Acceptable diff percentage (0-1, default 5%)
 * @param {string} [options.outputDiffPath='diff.png'] - Path to save the generated diff image
 * @returns {{
 *   match: boolean,
 *   diffPercentage: number,
 *   diffPixels: number,
 *   totalPixels: number,
 *   diffImagePath: string,
 *   dimensions: {
 *     design: { width: number, height: number },
 *     implementation: { width: number, height: number }
 *   }
 * }}
 */
export function compareScreenshots({
  designScreenshot,
  implementationScreenshot,
  threshold = 0.05,
  outputDiffPath = 'diff.png'
}) {
  const designImg = PNG.sync.read(readFileSync(designScreenshot));
  const implImg = PNG.sync.read(readFileSync(implementationScreenshot));

  const dimensions = {
    design: { width: designImg.width, height: designImg.height },
    implementation: { width: implImg.width, height: implImg.height }
  };

  // If dimensions differ, report mismatch immediately
  if (designImg.width !== implImg.width || designImg.height !== implImg.height) {
    return {
      match: false,
      diffPercentage: 100,
      diffPixels: designImg.width * designImg.height,
      totalPixels: designImg.width * designImg.height,
      diffImagePath: outputDiffPath,
      dimensions
    };
  }

  const { width, height } = designImg;
  const totalPixels = width * height;
  const diff = new PNG({ width, height });

  const diffPixels = pixelmatch(
    designImg.data,
    implImg.data,
    diff.data,
    width,
    height,
    { threshold: 0.1 }
  );

  // Ensure output directory exists
  const outputDir = dirname(resolve(outputDiffPath));
  mkdirSync(outputDir, { recursive: true });

  // Write diff image
  writeFileSync(outputDiffPath, PNG.sync.write(diff));

  const diffPercentage = (diffPixels / totalPixels) * 100;

  return {
    match: diffPercentage <= threshold * 100,
    diffPercentage,
    diffPixels,
    totalPixels,
    diffImagePath: resolve(outputDiffPath),
    dimensions
  };
}

// CLI mode: node scripts/compare-screenshots.js <design.png> <implementation.png> [--threshold 0.05] [--output diff.png]
const __filename = fileURLToPath(import.meta.url);
const scriptArg = process.argv[1] ? resolve(process.argv[1]) : '';

if (__filename === scriptArg) {
  const args = process.argv.slice(2);

  if (args.length < 2 || args[0] === '--help' || args[0] === '-h') {
    console.log('Usage: node scripts/compare-screenshots.js <design.png> <implementation.png> [--threshold 0.05] [--output diff.png]');
    console.log('');
    console.log('Options:');
    console.log('  --threshold <value>  Acceptable diff percentage as decimal (default: 0.05 = 5%)');
    console.log('  --output <path>      Path to save the diff image (default: diff.png)');
    process.exit(args[0] === '--help' || args[0] === '-h' ? 0 : 1);
  }

  const designScreenshot = args[0];
  const implementationScreenshot = args[1];
  let threshold = 0.05;
  let outputDiffPath = 'diff.png';

  for (let i = 2; i < args.length; i++) {
    if (args[i] === '--threshold' && args[i + 1]) {
      threshold = parseFloat(args[i + 1]);
      i++;
    } else if (args[i] === '--output' && args[i + 1]) {
      outputDiffPath = args[i + 1];
      i++;
    }
  }

  try {
    const result = compareScreenshots({
      designScreenshot,
      implementationScreenshot,
      threshold,
      outputDiffPath
    });

    console.log('Visual Comparison Report');
    console.log('========================');
    console.log(`Design:          ${designScreenshot} (${result.dimensions.design.width}x${result.dimensions.design.height})`);
    console.log(`Implementation:  ${implementationScreenshot} (${result.dimensions.implementation.width}x${result.dimensions.implementation.height})`);
    console.log(`Diff image:      ${result.diffImagePath}`);
    console.log(`Total pixels:    ${result.totalPixels}`);
    console.log(`Diff pixels:     ${result.diffPixels}`);
    console.log(`Diff percentage: ${result.diffPercentage.toFixed(2)}%`);
    console.log(`Threshold:       ${(threshold * 100).toFixed(2)}%`);
    console.log(`Result:          ${result.match ? '\u2713 MATCH' : '\u2717 MISMATCH'}`);

    process.exit(result.match ? 0 : 1);
  } catch (err) {
    console.error(`Error: ${err.message}`);
    process.exit(1);
  }
}
