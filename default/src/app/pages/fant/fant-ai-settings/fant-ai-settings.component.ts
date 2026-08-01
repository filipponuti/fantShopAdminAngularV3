import { Component, OnInit } from '@angular/core';
import { AbstractControl, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { finalize } from 'rxjs';

import {
  AiProviderId,
  AiProviderSettings,
  AiSettings,
  AiSettingsPayload,
  AiSettingsService
} from '../../../core/services/ai-settings.service';

interface ProviderView {
  id: AiProviderId;
  name: string;
  description: string;
  icon: string;
  color: string;
}

@Component({
  selector: 'app-fant-ai-settings',
  standalone: false,
  templateUrl: './fant-ai-settings.component.html',
  styleUrls: ['./fant-ai-settings.component.scss']
})
export class FantAiSettingsComponent implements OnInit {
  breadCrumbItems = [
    { label: 'Settings' },
    { label: 'AI', active: true }
  ];

  readonly providers: ProviderView[] = [
    {
      id: 'gemini',
      name: 'Gemini',
      description: 'API Google per la generazione di contenuti e immagini.',
      icon: 'ri-gemini-line',
      color: 'primary'
    },
    {
      id: 'openai',
      name: 'Chat GPT',
      description: 'API OpenAI per testi, immagini e composizione dei contenuti.',
      icon: 'ri-openai-fill',
      color: 'success'
    },
    {
      id: 'claude',
      name: 'Claude',
      description: 'API Anthropic per elaborazione e generazione dei contenuti.',
      icon: 'ri-sparkling-2-line',
      color: 'warning'
    }
  ];

  readonly form = this.formBuilder.group({
    gemini: this.createProviderGroup(),
    openai: this.createProviderGroup({ organization: '', project: '' }),
    claude: this.createProviderGroup({ apiVersion: '2023-06-01' })
  });

  loading = true;
  saving = false;
  errorMessage = '';
  successMessage = '';
  apiKeyConfigured: Record<AiProviderId, boolean> = {
    gemini: false,
    openai: false,
    claude: false
  };

  constructor(
    private readonly formBuilder: FormBuilder,
    private readonly aiSettingsService: AiSettingsService
  ) {}

  ngOnInit(): void {
    this.providers.forEach((provider) => {
      this.group(provider.id).get('enabled')?.valueChanges.subscribe(() => this.syncProviderState(provider.id));
    });
    this.loadSettings();
  }

  group(provider: AiProviderId): FormGroup {
    return this.form.controls[provider];
  }

  isEnabled(provider: AiProviderId): boolean {
    return Boolean(this.group(provider).get('enabled')?.value);
  }

  save(): void {
    this.errorMessage = '';
    this.successMessage = '';
    this.validateEnabledProviders();
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.errorMessage = 'Controlla i campi obbligatori delle sezioni attive.';
      return;
    }

    const raw = this.form.getRawValue();
    const payload = raw as AiSettingsPayload;
    this.saving = true;
    this.aiSettingsService.update(payload).pipe(
      finalize(() => this.saving = false)
    ).subscribe({
      next: (settings) => {
        this.applySettings(settings);
        this.successMessage = 'Impostazioni AI salvate correttamente.';
      },
      error: (error) => {
        this.errorMessage = error?.error?.message || 'Impossibile salvare le impostazioni AI.';
      }
    });
  }

  private loadSettings(): void {
    this.loading = true;
    this.aiSettingsService.get().pipe(
      finalize(() => this.loading = false)
    ).subscribe({
      next: (settings) => this.applySettings(settings),
      error: (error) => {
        this.errorMessage = error?.error?.message || 'Impossibile caricare le impostazioni AI.';
      }
    });
  }

  private applySettings(settings: AiSettings): void {
    this.providers.forEach(({ id }) => {
      const config = settings[id];
      this.apiKeyConfigured[id] = config.apiKeyConfigured;
      this.group(id).patchValue({
        ...config,
        apiKey: ''
      }, { emitEvent: false });
      this.syncProviderState(id);
    });
    this.form.markAsPristine();
  }

  private syncProviderState(provider: AiProviderId): void {
    const group = this.group(provider);
    const enabledControl = group.get('enabled');
    Object.entries(group.controls).forEach(([name, control]) => {
      if (name === 'enabled') {
        return;
      }
      if (enabledControl?.value) {
        control.enable({ emitEvent: false });
      } else {
        control.disable({ emitEvent: false });
      }
    });
    this.validateProvider(provider);
  }

  private validateEnabledProviders(): void {
    this.providers.forEach(({ id }) => this.validateProvider(id));
  }

  private validateProvider(provider: AiProviderId): void {
    const group = this.group(provider);
    const enabled = Boolean(group.get('enabled')?.value);
    const apiKeyControl = group.get('apiKey');
    const modelControl = group.get('model');
    const endpointControl = group.get('endpoint');

    modelControl?.setValidators(enabled ? [Validators.required] : []);
    endpointControl?.setValidators(enabled ? [Validators.required, Validators.pattern(/^https:\/\/.+/i)] : []);
    apiKeyControl?.setValidators(enabled && !this.apiKeyConfigured[provider] ? [Validators.required] : []);

    [apiKeyControl, modelControl, endpointControl].forEach((control) => control?.updateValueAndValidity({ emitEvent: false }));
  }

  private createProviderGroup(extra: Record<string, string> = {}): FormGroup {
    const controls: Record<string, AbstractControl> = {
      enabled: this.formBuilder.control(false),
      apiKey: this.formBuilder.control(''),
      model: this.formBuilder.control(''),
      endpoint: this.formBuilder.control(''),
      timeoutSeconds: this.formBuilder.control(60, [Validators.required, Validators.min(5), Validators.max(300)])
    };
    Object.entries(extra).forEach(([key, value]) => controls[key] = this.formBuilder.control(value));
    return this.formBuilder.group(controls);
  }
}
