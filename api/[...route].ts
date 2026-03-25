import type { IncomingMessage, ServerResponse } from "node:http";
import { createApp } from "../server/app";

let appPromise: Promise<Awaited<ReturnType<typeof createApp>>["app"]> | undefined;

async function getApp() {
  if (!appPromise) {
    appPromise = createApp().then(({ app }) => app);
  }

  return appPromise;
}

export default async function handler(req: IncomingMessage, res: ServerResponse) {
  const app = await getApp();
  return app(req as any, res as any);
}
