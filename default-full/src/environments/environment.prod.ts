declare const SITE_URL: string;

const defaultSiteUrl = 'https://www.filipponuti.it/agenti';
const siteUrl = (typeof SITE_URL === 'string' ? SITE_URL : defaultSiteUrl)
  .trim()
  .replace(/\/+$/, '');

export const environment = {
  production: true,
  defaultauth: 'wordpress',
  siteUrl,
  apiNamespace: 'fant-admin/v1',
  firebaseConfig: {
    apiKey: '',
    authDomain: '',
    databaseURL: '',
    projectId: '',
    storageBucket: '',
    messagingSenderId: '',
    appId: '',
    measurementId: ''
  }
};
