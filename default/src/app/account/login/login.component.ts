import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { finalize } from 'rxjs';

import { AuthenticationService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss'],
  standalone: false
})
export class LoginComponent implements OnInit {
  loginForm!: FormGroup;
  submitted = false;
  loading = false;
  fieldTextType = false;
  error = '';
  returnUrl = '/';
  readonly year = new Date().getFullYear();

  constructor(
    private readonly formBuilder: FormBuilder,
    private readonly authenticationService: AuthenticationService,
    private readonly router: Router,
    private readonly route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.returnUrl = this.safeReturnUrl(this.route.snapshot.queryParams['returnUrl']);

    if (this.authenticationService.isAuthenticated) {
      void this.router.navigateByUrl(this.returnUrl);
      return;
    }

    this.loginForm = this.formBuilder.group({
      login: ['', Validators.required],
      password: ['', Validators.required],
      remember: [false]
    });
  }

  get f() {
    return this.loginForm.controls;
  }

  onSubmit(): void {
    this.submitted = true;
    this.error = '';

    if (this.loginForm.invalid || this.loading) {
      return;
    }

    this.loading = true;
    this.authenticationService.login(
      this.f['login'].value,
      this.f['password'].value,
      !!this.f['remember'].value
    ).pipe(finalize(() => this.loading = false)).subscribe({
      next: () => void this.router.navigateByUrl(this.returnUrl),
      error: error => {
        this.error = error?.error?.message
          ?? 'Accesso non riuscito. Verifica le credenziali e riprova.';
      }
    });
  }

  toggleFieldTextType(): void {
    this.fieldTextType = !this.fieldTextType;
  }

  private safeReturnUrl(value: unknown): string {
    return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//')
      ? value
      : '/';
  }
}
