import { Component, TemplateRef } from '@angular/core';

import { ToastService } from './toast-service';

@Component({
    selector: 'app-toasts',
    template: `
   @for(toast of toastService.toasts;track $index){
     <ngb-toast
       [class]="toast.classname"
       [autohide]="true"
       [delay]="toast.delay || 5000"
       (hidden)="toastService.remove(toast)"
       >
       @if (isTemplate(toast)) {
         <ng-template [ngTemplateOutlet]="toast.textOrTpl"></ng-template>
       } @else {
         {{ toast.textOrTpl }}
       }
   
     </ngb-toast>}
   `,
    host: { '[class.ngb-toasts]': 'true' },
    standalone: false
})
export class ToastsContainer {
  constructor(public toastService: ToastService) { }

  isTemplate(toast: { textOrTpl: any; }) { return toast.textOrTpl instanceof TemplateRef; }
}
