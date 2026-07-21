import { Injectable } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, exhaustMap, map, tap } from 'rxjs/operators';
import { of } from 'rxjs';

import { AuthenticationService } from '../../core/services/auth.service';
import {
  Register,
  RegisterFailure,
  login,
  loginFailure,
  loginSuccess,
  logout,
  logoutSuccess
} from './authentication.actions';

@Injectable()
export class AuthenticationEffects {
  readonly register$ = createEffect(() => this.actions$.pipe(
    ofType(Register),
    map(() => RegisterFailure({
      error: 'La registrazione non è disponibile: gli account vengono gestiti da WordPress.'
    }))
  ));

  readonly login$ = createEffect(() => this.actions$.pipe(
    ofType(login),
    exhaustMap(({ email, password }) => this.authenticationService.login(email, password, false).pipe(
      map(session => loginSuccess({ user: session.user })),
      catchError(error => of(loginFailure({
        error: error?.error?.message ?? 'Accesso non riuscito.'
      })))
    ))
  ));

  readonly logout$ = createEffect(() => this.actions$.pipe(
    ofType(logout),
    tap(() => this.authenticationService.logout()),
    map(() => logoutSuccess())
  ));

  constructor(
    private readonly actions$: Actions,
    private readonly authenticationService: AuthenticationService
  ) {}
}
