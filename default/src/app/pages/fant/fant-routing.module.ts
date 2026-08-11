import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { FantHomeComponent } from './fant-home/fant-home.component';
import { FantCategorieComponent } from './fant-categorie/fant-categorie.component';
import { FantCataloghiComponent } from './fant-cataloghi/fant-cataloghi.component';
import { FantCataloghiArticoliComponent } from './fant-cataloghi-articoli/fant-cataloghi-articoli.component';
import { FantAiSettingsComponent } from './fant-ai-settings/fant-ai-settings.component';
import { FantCopertineComponent } from './fant-copertine/fant-copertine.component';
import { FantLayoutArticoliComponent } from './fant-layout-articoli/fant-layout-articoli.component';
import { FantLayoutArticoliDettaglioComponent } from './fant-layout-articoli-dettaglio/fant-layout-articoli-dettaglio.component';

const routes: Routes = [
  { path: 'fant-home', component: FantHomeComponent },
  { path: 'fant-categorie', component: FantCategorieComponent },
  { path: 'fant-cataloghi/:codice', component: FantCataloghiArticoliComponent },
  { path: 'fant-cataloghi', component: FantCataloghiComponent },
  { path: 'fant-layout-articoli/:codice', component: FantLayoutArticoliDettaglioComponent },
  { path: 'fant-layout-articoli', component: FantLayoutArticoliComponent },
  { path: 'fant-copertine', component: FantCopertineComponent },
  { path: 'fant-ai-settings', component: FantAiSettingsComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FantRoutingModule {}
