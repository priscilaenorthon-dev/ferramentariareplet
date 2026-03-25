import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();

const entrypoint = path.join(rootDir, "api", "[[...route]].ts");

assert.ok(fs.existsSync(entrypoint), "api/[[...route]].ts should exist");

const source = fs.readFileSync(entrypoint, "utf8");
assert.match(source, /export default/);
assert.match(source, /createApp/);
assert.match(source, /runtime\s*=\s*["']nodejs["']/);

const configPath = path.join(rootDir, "vercel.json");

assert.ok(fs.existsSync(configPath), "vercel.json should exist");

const config = JSON.parse(fs.readFileSync(configPath, "utf8"));

assert.equal(config.outputDirectory, "dist/public");
assert.ok(
  config.rewrites?.some(
    (rewrite) =>
      rewrite.source === "/((?!api(?:/|$)).*)" && rewrite.destination === "/index.html",
  ),
  "expected SPA fallback rewrite to skip API routes and index.html",
);
