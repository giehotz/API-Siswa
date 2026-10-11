/**
 * verify-extension.js
 * Validates manifest.json, file paths, CSP compliance, and extension integrity.
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const manifestPath = path.join(root, 'manifest.json');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

console.log('=== EXTENSION INTEGRITY VERIFICATION ===\n');

// 1. Manifest properties
assert.strictEqual(manifest.manifest_version, 3, 'Must be Manifest V3');
assert.ok(manifest.name, 'Extension name must be set');
assert.ok(manifest.version, 'Extension version must be set');
assert.ok(manifest.action && manifest.action.default_popup, 'Popup must be declared in action');
console.log('✓ Manifest V3 core structure valid.');

// 2. Icons
for (const [size, iconRel] of Object.entries(manifest.icons)) {
  const iconPath = path.join(root, iconRel);
  assert.ok(fs.existsSync(iconPath), `Icon ${size}px file must exist at ${iconPath}`);
  assert.ok(fs.statSync(iconPath).size > 100, `Icon ${size}px file must not be empty`);
  console.log(`✓ Icon ${size}px: exists (${fs.statSync(iconPath).size} bytes)`);
}

// 3. Popup HTML & CSP
const popupPath = path.join(root, manifest.action.default_popup);
assert.ok(fs.existsSync(popupPath), `Popup HTML must exist at ${popupPath}`);
const popupHtml = fs.readFileSync(popupPath, 'utf8');

const hasInlineScript = /<script(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/i.test(popupHtml);
assert.strictEqual(hasInlineScript, false, 'CSP Violation: Popup HTML must NOT contain inline scripts');
console.log('✓ Popup HTML exists and has NO inline scripts (CSP compliant).');

// 4. Scripts referenced in popup
const scriptRegex = /<script\s+src=["']([^"']+)["']/g;
let match;
while ((match = scriptRegex.exec(popupHtml)) !== null) {
  const scriptRel = match[1];
  const scriptAbs = path.resolve(path.dirname(popupPath), scriptRel);
  assert.ok(fs.existsSync(scriptAbs), `Referenced script ${scriptRel} must exist at ${scriptAbs}`);
  console.log(`✓ Script reference exists: ${scriptRel}`);
}

// 5. Content scripts & web accessible resources
for (const cs of manifest.content_scripts) {
  for (const js of cs.js) {
    const p = path.join(root, js);
    assert.ok(fs.existsSync(p), `Content script ${js} must exist`);
    console.log(`✓ Content script exists: ${js}`);
  }
}

for (const war of manifest.web_accessible_resources) {
  for (const res of war.resources) {
    const p = path.join(root, res);
    assert.ok(fs.existsSync(p), `Web accessible resource ${res} must exist`);
    console.log(`✓ Web accessible resource exists: ${res}`);
  }
}

// 6. CHROMEWEBSTORE.md
const cwsPath = path.join(root, 'CHROMEWEBSTORE.md');
assert.ok(fs.existsSync(cwsPath), 'CHROMEWEBSTORE.md must exist in root per user rule');
console.log('✓ CHROMEWEBSTORE.md exists in root.');

console.log('\n========================================');
console.log('EXTENSION VERIFICATION 100% PASSED!');
console.log('========================================');
