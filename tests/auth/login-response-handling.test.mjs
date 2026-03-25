import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();
const loginSource = fs.readFileSync(
  path.join(rootDir, "client", "src", "pages", "login.tsx"),
  "utf8",
);

assert.match(loginSource, /readApiResponse/);
assert.ok(
  !loginSource.includes("const error = await response.json();"),
  "login should not assume error responses are always JSON",
);
