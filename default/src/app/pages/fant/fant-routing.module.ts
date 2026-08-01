import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { FantHomeComponent } from './fant-home/fant-home.component';
import { FantCategorieComponent } from './fant-categorie/fant-categorie.component';
import { FantCataloghiComponent } from './fant-cataloghi/fant-cataloghi.component';
import { FantAiSettingsComponent } from './fant-ai-settings/fant-ai-settings.component';
import { FantCopertineComponent } from './fant-copertine/fant-copertine.component';

const routes: Routes = [
  { path: 'fant-home', component: FantHomeComponent },
  { path: 'fant-categorie', component: FantCategorieComponent },
  { path: 'fant-cataloghi', component: FantCataloghiComponent },
  { path: 'fant-copertine', component: FantCopertineComponent },
  { path: 'fant-ai-settings', component: FantAiSettingsComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FantRoutingModule {}
