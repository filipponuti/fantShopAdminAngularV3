import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { FantHomeComponent } from './fant-home/fant-home.component';
import { FantCategorieComponent } from './fant-categorie/fant-categorie.component';

const routes: Routes = [
  { path: 'fant-home', component: FantHomeComponent },
  { path: 'fant-categorie', component: FantCategorieComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FantRoutingModule {}

