import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();
const usersSource = fs.readFileSync(
  path.join(rootDir, "client", "src", "pages", "users.tsx"),
  "utf8",
);

assert.match(usersSource, /readApiResponse/);
assert.ok(
  !usersSource.includes("const error = await response.json();"),
  "user creation should not assume error responses are always JSON",
);
