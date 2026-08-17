import { Component, OnInit } from '@angular/core';
import { AbstractControl, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { finalize } from 'rxjs';

import {
  AI_PROVIDER_IDS,
  AiProviderId,
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
  free?: boolean;
  modelOptions?: string[];
  modelFreeText?: boolean;
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
    },
    {
      id: 'free-gemini',
      name: 'Gemini Free',
      description: 'Google AI Studio (tier gratuito) per prototipi e cataloghi.',
      icon: 'ri-gemini-line',
      color: 'info',
      free: true,
      modelOptions: ['gemini-1.5-flash', 'gemini-1.5-pro']
    },
    {
      id: 'free-groq',
      name: 'Groq Free',
      description: 'Groq Cloud: inferenza veloce su modelli open con piano free.',
      icon: 'ri-flashlight-line',
      color: 'danger',
      free: true,
      modelOptions: ['llama-3.1-8b-instant', 'llama-3.3-70b-versatile', 'mixtral-8x7b-32768']
    },
    {
      id: 'free-openrouter',
      name: 'OpenRouter Free',
      description: 'OpenRouter con modelli :free. Supporta anche stringhe modello libere.',
      icon: 'ri-route-line',
      color: 'secondary',
      free: true,
      modelFreeText: true,
      modelOptions: ['meta-llama/llama-3.1-8b-instruct:free']
    }
  ];

  readonly paidProviders = this.providers.filter(provider => !provider.free);
  readonly freeProviders = this.providers.filter(provider => provider.free);

  readonly form = this.formBuilder.group({
    gemini: this.createProviderGroup(),
    openai: this.createProviderGroup({ organization: '', project: '' }),
    claude: this.createProviderGroup({ apiVersion: '2023-06-01' }),
    'free-gemini': this.createProviderGroup({}, 'gemini-1.5-flash', 'https://generativelanguage.googleapis.com/v1beta'),
    'free-groq': this.createProviderGroup({}, 'llama-3.1-8b-instant', 'https://api.groq.com/openai/v1'),
    'free-openrouter': this.createProviderGroup(
      { siteUrl: '', appName: '' },
      'meta-llama/llama-3.1-8b-instruct:free',
      'https://openrouter.ai/api/v1',
    ),
  });

  loading = true;
  saving = false;
  errorMessage = '';
  successMessage = '';
  apiKeyConfigured: Record<AiProviderId, boolean> = {
    gemini: false,
    openai: false,
    claude: false,
    'free-gemini': false,
    'free-groq': false,
    'free-openrouter': false,
  };
  showApiKey: Record<AiProviderId, boolean> = {
    gemini: false,
    openai: false,
    claude: false,
    'free-gemini': false,
    'free-groq': false,
    'free-openrouter': false,
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
    return this.form.get(provider) as FormGroup;
  }

  isEnabled(provider: AiProviderId): boolean {
    return Boolean(this.group(provider).get('enabled')?.value);
  }

  toggleApiKeyVisibility(provider: AiProviderId): void {
    this.showApiKey = {
      ...this.showApiKey,
      [provider]: !this.showApiKey[provider],
    };
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

    const raw = this.form.getRawValue() as AiSettingsPayload;
    this.saving = true;
    this.aiSettingsService.update(raw).pipe(
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
    AI_PROVIDER_IDS.forEach((id) => {
      const config = settings[id];
      if (!config) {
        return;
      }
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

  private createProviderGroup(
    extra: Record<string, string> = {},
    defaultModel = '',
    defaultEndpoint = '',
  ): FormGroup {
    const controls: Record<string, AbstractControl> = {
      enabled: this.formBuilder.control(false),
      apiKey: this.formBuilder.control(''),
      model: this.formBuilder.control(defaultModel),
      endpoint: this.formBuilder.control(defaultEndpoint),
      timeoutSeconds: this.formBuilder.control(60, [Validators.required, Validators.min(5), Validators.max(300)])
    };
    Object.entries(extra).forEach(([key, value]) => controls[key] = this.formBuilder.control(value));
    return this.formBuilder.group(controls);
  }
}
