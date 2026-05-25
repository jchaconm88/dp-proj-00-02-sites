/**
 * Tests for compare-screenshots.js
 */

import { describe, it, beforeAll, afterAll } from 'vitest';
import { expect } from 'vitest';
import { writeFileSync, mkdirSync, rmSync, existsSync } from 'fs';
import { join } from 'path';
import { PNG } from 'pngjs';
import { compareScreenshots } from './compare-screenshots.js';

const TEST_DIR = join(import.meta.dirname, '__test_screenshots__');

function createTestPng(width, height, r, g, b, a = 255) {
  const png = new PNG({ width, height });
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const idx = (width * y + x) << 2;
      png.data[idx] = r;
      png.data[idx + 1] = g;
      png.data[idx + 2] = b;
      png.data[idx + 3] = a;
    }
  }
  return PNG.sync.write(png);
}

beforeAll(() => {
  mkdirSync(TEST_DIR, { recursive: true });
});

afterAll(() => {
  rmSync(TEST_DIR, { recursive: true, force: true });
});

describe('compareScreenshots', () => {
  it('returns match: true for identical images', () => {
    const imgPath1 = join(TEST_DIR, 'identical1.png');
    const imgPath2 = join(TEST_DIR, 'identical2.png');
    const diffPath = join(TEST_DIR, 'diff-identical.png');

    const pngData = createTestPng(100, 100, 255, 0, 0);
    writeFileSync(imgPath1, pngData);
    writeFileSync(imgPath2, pngData);

    const result = compareScreenshots({
      designScreenshot: imgPath1,
      implementationScreenshot: imgPath2,
      outputDiffPath: diffPath
    });

    expect(result.match).toBe(true);
    expect(result.diffPercentage).toBe(0);
    expect(result.diffPixels).toBe(0);
    expect(result.totalPixels).toBe(10000);
    expect(result.dimensions.design).toEqual({ width: 100, height: 100 });
    expect(result.dimensions.implementation).toEqual({ width: 100, height: 100 });
    expect(existsSync(diffPath)).toBe(true);
  });

  it('returns match: false for completely different images', () => {
    const imgPath1 = join(TEST_DIR, 'red.png');
    const imgPath2 = join(TEST_DIR, 'blue.png');
    const diffPath = join(TEST_DIR, 'diff-colors.png');

    writeFileSync(imgPath1, createTestPng(50, 50, 255, 0, 0));
    writeFileSync(imgPath2, createTestPng(50, 50, 0, 0, 255));

    const result = compareScreenshots({
      designScreenshot: imgPath1,
      implementationScreenshot: imgPath2,
      outputDiffPath: diffPath
    });

    expect(result.match).toBe(false);
    expect(result.diffPercentage).toBeGreaterThan(5);
    expect(result.diffPixels).toBeGreaterThan(0);
    expect(result.totalPixels).toBe(2500);
    expect(existsSync(diffPath)).toBe(true);
  });

  it('returns match: false when dimensions differ', () => {
    const imgPath1 = join(TEST_DIR, 'small.png');
    const imgPath2 = join(TEST_DIR, 'large.png');
    const diffPath = join(TEST_DIR, 'diff-size.png');

    writeFileSync(imgPath1, createTestPng(100, 100, 255, 0, 0));
    writeFileSync(imgPath2, createTestPng(200, 150, 255, 0, 0));

    const result = compareScreenshots({
      designScreenshot: imgPath1,
      implementationScreenshot: imgPath2,
      outputDiffPath: diffPath
    });

    expect(result.match).toBe(false);
    expect(result.diffPercentage).toBe(100);
    expect(result.dimensions.design).toEqual({ width: 100, height: 100 });
    expect(result.dimensions.implementation).toEqual({ width: 200, height: 150 });
  });

  it('uses default threshold of 0.05 (5%)', () => {
    const imgPath1 = join(TEST_DIR, 'base.png');
    const imgPath2 = join(TEST_DIR, 'slight-diff.png');
    const diffPath = join(TEST_DIR, 'diff-threshold.png');

    // Create a 100x100 red image
    const png1 = new PNG({ width: 100, height: 100 });
    const png2 = new PNG({ width: 100, height: 100 });

    for (let y = 0; y < 100; y++) {
      for (let x = 0; x < 100; x++) {
        const idx = (100 * y + x) << 2;
        png1.data[idx] = 255;
        png1.data[idx + 1] = 0;
        png1.data[idx + 2] = 0;
        png1.data[idx + 3] = 255;

        // Make 2% of pixels different (first 200 pixels are blue)
        if (y * 100 + x < 200) {
          png2.data[idx] = 0;
          png2.data[idx + 1] = 0;
          png2.data[idx + 2] = 255;
          png2.data[idx + 3] = 255;
        } else {
          png2.data[idx] = 255;
          png2.data[idx + 1] = 0;
          png2.data[idx + 2] = 0;
          png2.data[idx + 3] = 255;
        }
      }
    }

    writeFileSync(imgPath1, PNG.sync.write(png1));
    writeFileSync(imgPath2, PNG.sync.write(png2));

    const result = compareScreenshots({
      designScreenshot: imgPath1,
      implementationScreenshot: imgPath2,
      outputDiffPath: diffPath
    });

    // 2% diff is within 5% threshold
    expect(result.match).toBe(true);
    expect(result.diffPercentage).toBeLessThanOrEqual(5);
  });

  it('respects custom threshold', () => {
    const imgPath1 = join(TEST_DIR, 'custom-t1.png');
    const imgPath2 = join(TEST_DIR, 'custom-t2.png');
    const diffPath = join(TEST_DIR, 'diff-custom.png');

    // Create images with ~2% difference
    const png1 = new PNG({ width: 100, height: 100 });
    const png2 = new PNG({ width: 100, height: 100 });

    for (let y = 0; y < 100; y++) {
      for (let x = 0; x < 100; x++) {
        const idx = (100 * y + x) << 2;
        png1.data[idx] = 255;
        png1.data[idx + 1] = 0;
        png1.data[idx + 2] = 0;
        png1.data[idx + 3] = 255;

        if (y * 100 + x < 200) {
          png2.data[idx] = 0;
          png2.data[idx + 1] = 0;
          png2.data[idx + 2] = 255;
          png2.data[idx + 3] = 255;
        } else {
          png2.data[idx] = 255;
          png2.data[idx + 1] = 0;
          png2.data[idx + 2] = 0;
          png2.data[idx + 3] = 255;
        }
      }
    }

    writeFileSync(imgPath1, PNG.sync.write(png1));
    writeFileSync(imgPath2, PNG.sync.write(png2));

    // With threshold of 0.01 (1%), 2% diff should fail
    const result = compareScreenshots({
      designScreenshot: imgPath1,
      implementationScreenshot: imgPath2,
      threshold: 0.01,
      outputDiffPath: diffPath
    });

    expect(result.match).toBe(false);
  });

  it('generates a diff image file', () => {
    const imgPath1 = join(TEST_DIR, 'gen1.png');
    const imgPath2 = join(TEST_DIR, 'gen2.png');
    const diffPath = join(TEST_DIR, 'output', 'generated-diff.png');

    writeFileSync(imgPath1, createTestPng(50, 50, 255, 0, 0));
    writeFileSync(imgPath2, createTestPng(50, 50, 0, 255, 0));

    const result = compareScreenshots({
      designScreenshot: imgPath1,
      implementationScreenshot: imgPath2,
      outputDiffPath: diffPath
    });

    expect(existsSync(diffPath)).toBe(true);
    expect(result.diffImagePath).toContain('generated-diff.png');
  });
});
