import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { DragDropModule } from '@angular/cdk/drag-drop';

import { SharedModule } from '../../shared/shared.module';
import { FantRoutingModule } from './fant-routing.module';
import { FantHomeComponent } from './fant-home/fant-home.component';
import { FantCategorieComponent } from './fant-categorie/fant-categorie.component';

@NgModule({
  declarations: [FantHomeComponent, FantCategorieComponent],
  imports: [
    CommonModule,
    ReactiveFormsModule,
    DragDropModule,
    SharedModule,
    FantRoutingModule
  ]
})
export class FantModule {}

