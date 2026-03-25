import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();

const authSource = fs.readFileSync(path.join(rootDir, "server", "auth.ts"), "utf8");

assert.match(authSource, /connect-pg-simple/);
assert.match(authSource, /createTableIfMissing:\s*false/);
assert.match(authSource, /tableName:\s*"sessions"/);
assert.match(authSource, /app\.set\(['"]trust proxy['"],\s*1\)/);
assert.match(authSource, /secure:\s*process\.env\.NODE_ENV === "production"/);
