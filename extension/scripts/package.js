const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..');
const distDir = path.join(rootDir, 'dist');
const stagingDir = path.join(distDir, 'staging');
const zipFile = path.join(rootDir, 'emis-student-exporter-v1.2.0.zip');

if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}
if (fs.existsSync(stagingDir)) {
  fs.rmSync(stagingDir, { recursive: true, force: true });
}
fs.mkdirSync(stagingDir, { recursive: true });

function copyRecursive(src, dest) {
  const stats = fs.statSync(src);
  if (stats.isDirectory()) {
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    for (const item of fs.readdirSync(src)) {
      copyRecursive(path.join(src, item), path.join(dest, item));
    }
  } else {
    fs.copyFileSync(src, dest);
  }
}

// 1. Copy manifest
fs.copyFileSync(path.join(rootDir, 'manifest.json'), path.join(stagingDir, 'manifest.json'));

// 2. Copy icons, lib, popup, vendor
['icons', 'lib', 'popup', 'vendor'].forEach((dir) => {
  copyRecursive(path.join(rootDir, dir), path.join(stagingDir, dir));
});

// 3. Copy scripts (only runtime content.js and inpage-sniffer.js)
const stagingScripts = path.join(stagingDir, 'scripts');
fs.mkdirSync(stagingScripts, { recursive: true });
['content.js', 'inpage-sniffer.js'].forEach((file) => {
  fs.copyFileSync(path.join(rootDir, 'scripts', file), path.join(stagingScripts, file));
});

// 4. Compress to zip
if (fs.existsSync(zipFile)) {
  fs.unlinkSync(zipFile);
}

try {
  execSync(`powershell -NoProfile -Command "Compress-Archive -Path '${stagingDir}\\*' -DestinationPath '${zipFile}' -Force"`, { stdio: 'inherit' });
  console.log(`Successfully packaged extension to ${zipFile}`);
} finally {
  fs.rmSync(stagingDir, { recursive: true, force: true });
}
