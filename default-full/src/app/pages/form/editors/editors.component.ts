import { Component, OnInit } from '@angular/core';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';

@Component({
    selector: 'app-editors',
    templateUrl: './editors.component.html',
    styleUrls: ['./editors.component.scss'],
    standalone: false
})

/**
 * Editors Component
 */
export class EditorsComponent implements OnInit {
  public Editor = ClassicEditor;
  // bread crumb items
  breadCrumbItems!: Array<{}>;
  public editorData = '<p>Hello, CKEditor 5!</p>';

  constructor() { }

  ngOnInit(): void {
    /**
    * BreadCrumb
    */
     this.breadCrumbItems = [
      { label: 'Forms' },
      { label: 'Editors', active: true }
    ];
  }

}
