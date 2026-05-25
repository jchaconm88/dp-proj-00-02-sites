/**
 * Helper module for template packaging inclusion/exclusion logic.
 * Mirrors the rules in package-template.ps1 for testability in JavaScript.
 *
 * Inclusion/Exclusion Rules (evaluated in order):
 * 1. EXCLUDE *.example.json
 * 2. EXCLUDE *.md (README.md and documentation)
 * 3. INCLUDE .html files
 * 4. INCLUDE .json files (manifest and config)
 * 5. INCLUDE files in partials/ directory
 * 6. INCLUDE files in styles/ directory
 * 7. Otherwise: EXCLUDE
 */

/**
 * Determines whether a file should be included in the template package
 * based on its relative path within the components/ directory.
 *
 * @param {string} relativePath - The file path relative to components/ (using forward slashes)
 * @returns {boolean} true if the file should be included, false if excluded
 */
export function shouldIncludeFile(relativePath) {
  if (!relativePath || typeof relativePath !== 'string') {
    return false;
  }

  // Normalize path separators to forward slashes
  const normalized = relativePath.replace(/\\/g, '/');
  const fileName = normalized.split('/').pop() || '';
  const extension = getExtension(fileName);

  // --- Exclusion rules (checked first) ---

  // Exclude *.example.json
  if (fileName.endsWith('.example.json')) {
    return false;
  }

  // Exclude *.md (README.md and all documentation files)
  if (extension === '.md') {
    return false;
  }

  // --- Inclusion rules ---

  // Include .html files
  if (extension === '.html') {
    return true;
  }

  // Include .json files (manifest and other config)
  if (extension === '.json') {
    return true;
  }

  // Include files in partials/ directory
  if (normalized.startsWith('partials/') || normalized === 'partials') {
    return true;
  }

  // Include files in styles/ directory
  if (normalized.startsWith('styles/') || normalized === 'styles') {
    return true;
  }

  // Default: exclude
  return false;
}

/**
 * Gets the file extension (lowercase, including the dot).
 * @param {string} fileName
 * @returns {string}
 */
function getExtension(fileName) {
  const lastDot = fileName.lastIndexOf('.');
  if (lastDot === -1 || lastDot === 0) {
    return '';
  }
  return fileName.slice(lastDot).toLowerCase();
}
