import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';

import { BreadcrumbsComponent } from './breadcrumbs/breadcrumbs.component';
import { ScrollspyDirective } from './scrollspy.directive';

@NgModule({
  declarations: [
    BreadcrumbsComponent,
    ScrollspyDirective
  ],
  imports: [
    CommonModule,
    RouterModule,
  ],
  exports: [BreadcrumbsComponent, ScrollspyDirective]
})
export class SharedModule { }
