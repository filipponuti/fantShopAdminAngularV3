import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { BreadcrumbsComponent } from './breadcrumbs/breadcrumbs.component';
import { ScrollspyDirective } from './scrollspy.directive';

@NgModule({
  declarations: [
    BreadcrumbsComponent,
    ScrollspyDirective
  ],
  imports: [
    CommonModule
  ],
  exports: [BreadcrumbsComponent, ScrollspyDirective]
})
export class SharedModule { }
