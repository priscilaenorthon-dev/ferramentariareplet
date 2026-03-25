import { Pool, neonConfig } from '@neondatabase/serverless';
import { drizzle } from 'drizzle-orm/neon-serverless';
import ws from "ws";
import * as schema from "../shared/schema";

neonConfig.webSocketConstructor = ws;

const databaseUrl = process.env.DATABASE_URL;

export const pool = databaseUrl ? new Pool({ connectionString: databaseUrl }) : null;

const missingDatabaseProxy = new Proxy({}, {
  get() {
    throw new Error(
      "DATABASE_URL must be set. Did you forget to provision a database?",
    );
  },
});

export const db = (pool
  ? drizzle({ client: pool, schema })
  : missingDatabaseProxy) as ReturnType<typeof drizzle<typeof schema>>;

export async function getDatabaseHealth() {
  if (!databaseUrl || !pool) {
    return {
      configured: false,
      connected: false,
      database: null,
      user: null,
      error: "DATABASE_URL is not configured",
    };
  }

  try {
    const result = await pool.query(
      "select current_database() as database, current_user as user",
    );
    const row = result.rows[0] as { database?: string; user?: string } | undefined;

    return {
      configured: true,
      connected: true,
      database: row?.database ?? null,
      user: row?.user ?? null,
      error: null,
    };
  } catch (error) {
    return {
      configured: true,
      connected: false,
      database: null,
      user: null,
      error: error instanceof Error ? error.message : String(error),
    };
  }
}
