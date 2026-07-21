export class User {
  id?: number;
  username?: string;
  firstName?: string;
  lastName?: string;
  email?: string;
  displayName?: string;
  roles?: string[];
  avatarUrl?: string;
}

export interface AuthSession {
  accessToken: string;
  refreshToken: string;
  tokenType: 'Bearer';
  expiresIn: number;
  user: User;
}
