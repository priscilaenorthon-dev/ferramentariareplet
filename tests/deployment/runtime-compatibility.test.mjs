import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();

const viteServerSource = fs.readFileSync(
  path.join(rootDir, "server", "vite.ts"),
  "utf8",
);

assert.match(
  viteServerSource,
  /path\.resolve\(import\.meta\.dirname,\s*"\.\.",\s*"dist",\s*"public"\)/,
  "serveStatic should serve the built client from dist/public",
);

const packageJson = JSON.parse(
  fs.readFileSync(path.join(rootDir, "package.json"), "utf8"),
);

assert.ok(
  typeof packageJson.scripts?.dev === "string" &&
    !packageJson.scripts.dev.includes("NODE_ENV="),
  "dev script should be shell-agnostic and avoid Unix-only NODE_ENV assignment",
);

assert.ok(
  typeof packageJson.scripts?.start === "string" &&
    !packageJson.scripts.start.includes("NODE_ENV="),
  "start script should be shell-agnostic and avoid Unix-only NODE_ENV assignment",
);
