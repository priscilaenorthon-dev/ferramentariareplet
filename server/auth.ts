import type { Express, Request, Response, NextFunction } from "express";
import session from "express-session";
import connectPg from "connect-pg-simple";
import bcrypt from "bcryptjs";
import { storage } from "./storage";
import type { UserRole } from "../shared/schema";

const SALT_ROUNDS = 10;
const SESSION_TTL_MS = 1000 * 60 * 60 * 24 * 7;

// Extend Express Request to include user
declare global {
  namespace Express {
    interface Request {
      user?: {
        id: string;
        username: string;
        role: UserRole;
        firstName?: string;
        lastName?: string;
        email?: string | null;
      };
    }
  }
}

export async function setupAuth(app: Express) {
  if (process.env.NODE_ENV === "production") {
    app.set('trust proxy', 1);
  }

  app.use(createSessionMiddleware());
}

function getSessionSecret(): string {
  if (process.env.SESSION_SECRET) {
    return process.env.SESSION_SECRET;
  }

  if (process.env.NODE_ENV === "production") {
    throw new Error("SESSION_SECRET must be set in production");
  }

  return "ferramentaria-secret-key-change-in-development";
}

function createSessionMiddleware() {
  if (!process.env.DATABASE_URL) {
    throw new Error("DATABASE_URL must be set before configuring sessions");
  }

  const PgSessionStore = connectPg(session);
  const sessionStore = new PgSessionStore({
    conString: process.env.DATABASE_URL,
    createTableIfMissing: false,
    tableName: "sessions",
    ttl: Math.floor(SESSION_TTL_MS / 1000),
  });

  return session({
    name: "ferramentaria.sid",
    secret: getSessionSecret(),
    store: sessionStore,
    resave: false,
    saveUninitialized: false,
    proxy: process.env.NODE_ENV === "production",
    cookie: {
      secure: process.env.NODE_ENV === "production",
      httpOnly: true,
      sameSite: "lax",
      maxAge: SESSION_TTL_MS,
    },
  });
}

export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, SALT_ROUNDS);
}

export async function verifyPassword(password: string, hash: string): Promise<boolean> {
  return bcrypt.compare(password, hash);
}

export function isAuthenticated(req: Request, res: Response, next: NextFunction) {
  if (req.session && (req.session as any).userId) {
    next();
  } else {
    res.status(401).json({ message: "Unauthorized" });
  }
}

export function authorizeRoles(...roles: UserRole[]) {
  return (req: Request, res: Response, next: NextFunction) => {
    if (req.user && roles.includes(req.user.role)) {
      return next();
    }
    res.status(403).json({ message: "Forbidden" });
  };
}

export function isAdmin(req: Request, res: Response, next: NextFunction) {
  return authorizeRoles("admin")(req, res, next);
}

export function isOperatorOrAdmin(req: Request, res: Response, next: NextFunction) {
  return authorizeRoles("admin", "operator")(req, res, next);
}

// Middleware to load user from session
export async function loadUserFromSession(req: Request, res: Response, next: NextFunction) {
  if (req.session && (req.session as any).userId) {
    try {
      const user = await storage.getUser((req.session as any).userId);
      if (user) {
        req.user = {
          id: user.id,
          username: user.username,
          role: (user.role as UserRole) ?? "user",
          firstName: user.firstName,
          lastName: user.lastName,
          email: user.email,
        };
      }
    } catch (error) {
      console.error("Error loading user from session:", error);
    }
  }
  next();
}
