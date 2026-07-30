import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, catchError, of, tap, throwError } from 'rxjs';

import { environment } from '../../../environments/environment';
import { AuthSession, User } from '../../store/Authentication/auth.models';

const USER_KEY = 'fant-admin-user';
const ACCESS_TOKEN_KEY = 'fant-admin-access-token';
const REFRESH_TOKEN_KEY = 'fant-admin-refresh-token';
const DEVICE_ID_KEY = 'fant-admin-device-id';

@Injectable({ providedIn: 'root' })
export class AuthenticationService {
  private readonly apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}`;
  private readonly currentUserSubject = new BehaviorSubject<User | null>(this.readUser());

  readonly currentUser$ = this.currentUserSubject.asObservable();

  constructor(private readonly http: HttpClient) {}

  get currentUserValue(): User | null {
    return this.currentUserSubject.value;
  }

  get accessToken(): string | null {
    return this.readStorageValue(ACCESS_TOKEN_KEY);
  }

  get refreshToken(): string | null {
    return this.readStorageValue(REFRESH_TOKEN_KEY);
  }

  get isAuthenticated(): boolean {
    return !!this.accessToken && !!this.currentUserValue;
  }

  isApiRequest(url: string): boolean {
    return url.startsWith(this.apiUrl);
  }

  isAuthRequest(url: string): boolean {
    return url.startsWith(`${this.apiUrl}/auth/`);
  }

  login(login: string, password: string, remember: boolean): Observable<AuthSession> {
    return this.http.post<AuthSession>(`${this.apiUrl}/auth/login`, {
      login,
      password,
      deviceId: this.deviceId(),
      deviceName: this.deviceName()
    }).pipe(tap(session => this.saveSession(session, remember)));
  }

  register(_email: string, _name: string, _password: string): Observable<never> {
    return throwError(() => new Error(
      'La registrazione non è disponibile: gli account vengono gestiti da WordPress.'
    ));
  }

  refresh(): Observable<AuthSession> {
    const refreshToken = this.refreshToken;
    if (!refreshToken) {
      return throwError(() => new Error('Sessione non disponibile.'));
    }

    const remember = localStorage.getItem(REFRESH_TOKEN_KEY) !== null;
    return this.http.post<AuthSession>(`${this.apiUrl}/auth/refresh`, { refreshToken }).pipe(
      tap(session => this.saveSession(session, remember))
    );
  }

  me(): Observable<User> {
    return this.http.get<User>(`${this.apiUrl}/me`).pipe(
      tap(user => this.currentUserSubject.next(user))
    );
  }

  logout(): void {
    const refreshToken = this.refreshToken;
    this.clearSession();

    if (refreshToken) {
      this.http.post<void>(`${this.apiUrl}/auth/logout`, { refreshToken })
        .pipe(catchError(() => of(undefined)))
        .subscribe();
    }
  }

  clearSession(): void {
    for (const storage of [sessionStorage, localStorage]) {
      storage.removeItem(USER_KEY);
      storage.removeItem(ACCESS_TOKEN_KEY);
      storage.removeItem(REFRESH_TOKEN_KEY);
      // Remove the old Velzon keys during migration.
      storage.removeItem('currentUser');
      storage.removeItem('token');
      storage.removeItem('auth-token');
    }
    this.currentUserSubject.next(null);
  }

  private saveSession(session: AuthSession, remember: boolean): void {
    this.clearStoredSessionValues();
    const storage = remember ? localStorage : sessionStorage;
    storage.setItem(USER_KEY, JSON.stringify(session.user));
    storage.setItem(ACCESS_TOKEN_KEY, session.accessToken);
    storage.setItem(REFRESH_TOKEN_KEY, session.refreshToken);
    this.currentUserSubject.next(session.user);
  }

  private clearStoredSessionValues(): void {
    for (const storage of [sessionStorage, localStorage]) {
      storage.removeItem(USER_KEY);
      storage.removeItem(ACCESS_TOKEN_KEY);
      storage.removeItem(REFRESH_TOKEN_KEY);
      storage.removeItem('currentUser');
      storage.removeItem('token');
    }
  }

  private readUser(): User | null {
    const value = this.readStorageValue(USER_KEY);
    if (!value) {
      return null;
    }

    try {
      return JSON.parse(value) as User;
    } catch {
      return null;
    }
  }

  private readStorageValue(key: string): string | null {
    return sessionStorage.getItem(key) ?? localStorage.getItem(key);
  }

  private deviceId(): string {
    let deviceId = localStorage.getItem(DEVICE_ID_KEY);
    if (!deviceId) {
      deviceId = crypto.randomUUID();
      localStorage.setItem(DEVICE_ID_KEY, deviceId);
    }
    return deviceId;
  }

  private deviceName(): string {
    return `${navigator.platform || 'Browser'} - ${navigator.userAgent.slice(0, 120)}`;
  }
}
