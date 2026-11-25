/**
 * Render the WordPress.org banner and icon PNGs from the HTML sources next to this file.
 *
 *   npm install --prefix /tmp/canada-post-render playwright-core
 *   PLAYWRIGHT_DIR=/tmp/canada-post-render node .wordpress-org/src/render.mjs
 *
 * Uses the installed Google Chrome (set CHROME_PATH to use another browser).
 * The banner loads its fonts from Google Fonts, so render while online.
 */
import { createRequire } from 'node:module'
import { fileURLToPath, pathToFileURL } from 'node:url'
import path from 'node:path'

const here = path.dirname(fileURLToPath(import.meta.url))
const out = path.resolve(here, '..')
const require = createRequire(process.env.PLAYWRIGHT_DIR ? path.join(process.env.PLAYWRIGHT_DIR, 'package.json') : import.meta.url)
const { chromium } = require('playwright-core')

const chrome = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'

const jobs = [
  { source: 'banner.html', width: 772, height: 250, files: { 1: 'banner-772x250.png', 2: 'banner-1544x500.png' } },
  { source: 'icon.html', width: 128, height: 128, files: { 1: 'icon-128x128.png', 2: 'icon-256x256.png' } },
]

const browser = await chromium.launch({ executablePath: chrome })
try {
  for (const job of jobs) {
    for (const [scale, file] of Object.entries(job.files)) {
      const page = await browser.newPage({ viewport: { width: job.width, height: job.height }, deviceScaleFactor: Number(scale) })
      await page.goto(pathToFileURL(path.join(here, job.source)).href, { waitUntil: 'networkidle' })
      await page.evaluate(() => document.fonts.ready)
      await page.screenshot({ path: path.join(out, file), omitBackground: false })
      await page.close()
      console.log(`${file} (${job.width * scale}x${job.height * scale})`)
    }
  }
} finally {
  await browser.close()
}
