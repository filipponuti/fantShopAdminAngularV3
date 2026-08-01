import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { DragDropModule } from '@angular/cdk/drag-drop';
import { NgbPaginationModule } from '@ng-bootstrap/ng-bootstrap';

import { SharedModule } from '../../shared/shared.module';
import { FantRoutingModule } from './fant-routing.module';
import { FantHomeComponent } from './fant-home/fant-home.component';
import { FantCategorieComponent } from './fant-categorie/fant-categorie.component';
import { FantCataloghiComponent } from './fant-cataloghi/fant-cataloghi.component';
import { FantAiSettingsComponent } from './fant-ai-settings/fant-ai-settings.component';

@NgModule({
  declarations: [FantHomeComponent, FantCategorieComponent, FantCataloghiComponent, FantAiSettingsComponent],
  imports: [
    CommonModule,
    ReactiveFormsModule,
    DragDropModule,
    NgbPaginationModule,
    SharedModule,
    FantRoutingModule
  ]
})
export class FantModule {}
