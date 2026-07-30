import { Injectable } from '@angular/core';
import {
  HttpErrorResponse,
  HttpEvent,
  HttpHandler,
  HttpInterceptor,
  HttpRequest
} from '@angular/common/http';
import { Router } from '@angular/router';
import {
  BehaviorSubject,
  Observable,
  catchError,
  filter,
  finalize,
  switchMap,
  take,
  throwError
} from 'rxjs';

import { AuthenticationService } from '../services/auth.service';

@Injectable()
export class JwtInterceptor implements HttpInterceptor {
  private refreshing = false;
  private readonly refreshedToken$ = new BehaviorSubject<string | null>(null);

  constructor(
    private readonly authenticationService: AuthenticationService,
    private readonly router: Router
  ) {}

  intercept(request: HttpRequest<unknown>, next: HttpHandler): Observable<HttpEvent<unknown>> {
    const isApiRequest = this.authenticationService.isApiRequest(request.url);
    const isAuthRequest = this.authenticationService.isAuthRequest(request.url);
    const token = this.authenticationService.accessToken;
    const authenticatedRequest = isApiRequest && !isAuthRequest && token
      ? this.withToken(request, token)
      : request;

    return next.handle(authenticatedRequest).pipe(
      catchError(error => {
        if (
          error instanceof HttpErrorResponse &&
          error.status === 401 &&
          isApiRequest &&
          !isAuthRequest &&
          this.authenticationService.refreshToken
        ) {
          return this.handleUnauthorized(request, next);
        }
        return throwError(() => error);
      })
    );
  }

  private handleUnauthorized(
    request: HttpRequest<unknown>,
    next: HttpHandler
  ): Observable<HttpEvent<unknown>> {
    if (this.refreshing) {
      return this.refreshedToken$.pipe(
        filter((token): token is string => token !== null),
        take(1),
        switchMap(token => next.handle(this.withToken(request, token)))
      );
    }

    this.refreshing = true;
    this.refreshedToken$.next(null);

    return this.authenticationService.refresh().pipe(
      switchMap(session => {
        this.refreshedToken$.next(session.accessToken);
        return next.handle(this.withToken(request, session.accessToken));
      }),
      catchError(error => {
        this.authenticationService.clearSession();
        void this.router.navigate(['/auth/login']);
        return throwError(() => error);
      }),
      finalize(() => {
        this.refreshing = false;
      })
    );
  }

  private withToken(request: HttpRequest<unknown>, token: string): HttpRequest<unknown> {
    return request.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
  }
}
