import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();

const apiBaseSource = fs.readFileSync(
  path.join(rootDir, "client", "src", "lib", "apiBase.ts"),
  "utf8",
);

assert.match(apiBaseSource, /window\.location\.origin/);
assert.ok(!apiBaseSource.includes("/replit/php-api"));
assert.ok(!apiBaseSource.includes("http://localhost/replit/php-api"));

const viteConfigSource = fs.readFileSync(path.join(rootDir, "vite.config.ts"), "utf8");

assert.ok(!viteConfigSource.includes('base: "./"'));
assert.match(viteConfigSource, /base:\s*process\.env\.[A-Z0-9_]+/);
