import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

export type AiProviderId =
  | 'gemini'
  | 'openai'
  | 'claude'
  | 'free-gemini'
  | 'free-groq'
  | 'free-openrouter';

export const AI_PROVIDER_IDS: AiProviderId[] = [
  'gemini',
  'openai',
  'claude',
  'free-gemini',
  'free-groq',
  'free-openrouter',
];

export interface AiProviderSettings {
  enabled: boolean;
  apiKeyConfigured: boolean;
  model: string;
  endpoint: string;
  timeoutSeconds: number;
  organization?: string;
  project?: string;
  apiVersion?: string;
  siteUrl?: string;
  appName?: string;
}

export type AiSettings = Record<AiProviderId, AiProviderSettings>;

export interface AiProviderSettingsPayload {
  enabled: boolean;
  apiKey: string;
  model: string;
  endpoint: string;
  timeoutSeconds: number;
  organization?: string;
  project?: string;
  apiVersion?: string;
  siteUrl?: string;
  appName?: string;
}

export type AiSettingsPayload = Record<AiProviderId, AiProviderSettingsPayload>;

@Injectable({ providedIn: 'root' })
export class AiSettingsService {
  private readonly apiUrl = `${environment.siteUrl}/wp-json/${environment.apiNamespace}/settings/ai`;

  constructor(private readonly http: HttpClient) {}

  get(): Observable<AiSettings> {
    return this.http.get<AiSettings>(this.apiUrl);
  }

  update(payload: AiSettingsPayload): Observable<AiSettings> {
    return this.http.put<AiSettings>(this.apiUrl, payload);
  }
}
