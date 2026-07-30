// This file can be replaced during build by using the `fileReplacements` array.
// `ng build` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

declare const SITE_URL: string;

const defaultSiteUrl = 'https://www.filipponuti.it/agenti';
const siteUrl = (typeof SITE_URL === 'string' ? SITE_URL : defaultSiteUrl)
  .trim()
  .replace(/\/+$/, '');

export const environment = {
  production: false,
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

/*
 * For easier debugging in development mode, you can import the following file
 * to ignore zone related error stack frames such as `zone.run`, `zoneDelegate.invokeTask`.
 *
 * This import should be commented out in production mode because it will have a negative impact
 * on performance if an error is thrown.
 */
// import 'zone.js/plugins/zone-error';  // Included with Angular CLI.
