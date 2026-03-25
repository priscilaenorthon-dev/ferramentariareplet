import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();

const routesSource = fs.readFileSync(path.join(rootDir, "server", "routes.ts"), "utf8");
const dbSource = fs.readFileSync(path.join(rootDir, "server", "db.ts"), "utf8");

assert.match(routesSource, /app\.get\(['"]\/api\/health['"]/);
assert.match(routesSource, /getDatabaseHealth/);
assert.match(dbSource, /export async function getDatabaseHealth/);
assert.match(dbSource, /select current_database\(\)/);
