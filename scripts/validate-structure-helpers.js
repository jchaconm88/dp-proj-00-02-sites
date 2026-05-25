/**
 * Helper module for design directory structure validation.
 * JavaScript equivalent of the Test-DesignDirectory function in validate-structure.ps1.
 */

const REQUIRED_FILES = ['code.html', 'DESIGN.md', 'screen.png'];

/**
 * Validates that a design directory contains all required files.
 *
 * @param {string[]} files - Array of filenames present in the design/ directory.
 * @returns {{ valid: true } | { valid: false, missingFiles: string[] }}
 */
export function validateDesignDirectory(files) {
  const fileSet = new Set(files);
  const missingFiles = REQUIRED_FILES.filter((f) => !fileSet.has(f));

  if (missingFiles.length === 0) {
    return { valid: true };
  }

  return { valid: false, missingFiles };
}

export { REQUIRED_FILES };
