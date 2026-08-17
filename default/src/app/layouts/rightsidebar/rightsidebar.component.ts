import { Component, OnInit, Output, EventEmitter } from '@angular/core';

@Component({
    selector: 'app-rightsidebar',
    templateUrl: './rightsidebar.component.html',
    styleUrls: ['./rightsidebar.component.scss'],
    standalone: false
})

/**
 * Right Sidebar component (back-to-top + preloader only)
 */
export class RightsidebarComponent implements OnInit {

  @Output() settingsButtonClicked = new EventEmitter();

  constructor() { }

  ngOnInit(): void {}

  /**
   * When the user clicks on the button, scroll to the top of the document
   */
  topFunction() {
    document.body.scrollTop = 0;
    document.documentElement.scrollTop = 0;
  }

}
