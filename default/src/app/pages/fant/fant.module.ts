import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { DragDropModule } from '@angular/cdk/drag-drop';
import { NgbNavModule, NgbPaginationModule } from '@ng-bootstrap/ng-bootstrap';
import { CKEditorModule } from '@ckeditor/ckeditor5-angular';

import { SharedModule } from '../../shared/shared.module';
import { FantRoutingModule } from './fant-routing.module';
import { FantHomeComponent } from './fant-home/fant-home.component';
import { FantCategorieComponent } from './fant-categorie/fant-categorie.component';
import { FantCataloghiComponent } from './fant-cataloghi/fant-cataloghi.component';
import { FantCataloghiArticoliComponent } from './fant-cataloghi-articoli/fant-cataloghi-articoli.component';
import { FantAiSettingsComponent } from './fant-ai-settings/fant-ai-settings.component';
import { FantCopertineComponent } from './fant-copertine/fant-copertine.component';
import { FantCopertineDettaglioComponent } from './fant-copertine-dettaglio/fant-copertine-dettaglio.component';
import { FantLayoutArticoliComponent } from './fant-layout-articoli/fant-layout-articoli.component';
import { FantLayoutArticoliDettaglioComponent } from './fant-layout-articoli-dettaglio/fant-layout-articoli-dettaglio.component';

@NgModule({
  declarations: [
    FantHomeComponent,
    FantCategorieComponent,
    FantCataloghiComponent,
    FantCataloghiArticoliComponent,
    FantLayoutArticoliComponent,
    FantLayoutArticoliDettaglioComponent,
    FantCopertineComponent,
    FantCopertineDettaglioComponent,
    FantAiSettingsComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    DragDropModule,
    NgbNavModule,
    NgbPaginationModule,
    CKEditorModule,
    SharedModule,
    FantRoutingModule
  ]
})
export class FantModule {}
