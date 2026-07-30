import { Component, OnInit } from '@angular/core';

@Component({
    selector: 'app-job',
    templateUrl: './job.component.html',
    styleUrls: ['./job.component.scss'],
    standalone: false
})
export class JobComponent implements OnInit {

  currentSection = 'home';
  public isCollapsed = true;

  constructor() { }

  ngOnInit(): void {
  }

  /**
* Section changed method
* @param sectionId specify the current sectionID
*/
  onSectionChange(sectionId: string) {
    this.currentSection = sectionId;
  }


  scrollTo(section: string, offset: number = 0) {
    const element = document.getElementById(section);
    if (element) {
      const topPos = element.getBoundingClientRect().top + window.pageYOffset + offset;
      window.scrollTo({
        top: topPos,
        behavior: 'smooth'
      });
    }

    this.onSectionChange(section);
  }
  /**
    * Window scroll method
    */
  // tslint:disable-next-line: typedef
  windowScroll() {
    const navbar = document.getElementById('navbar');
    if (document.body.scrollTop > 40 || document.documentElement.scrollTop > 40) {
      navbar?.classList.add('is-sticky');
    }
    else {
      navbar?.classList.remove('is-sticky');
    }

    // Top Btn Set
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
      (document.getElementById("back-to-top") as HTMLElement).style.display = "block"
    } else {
      (document.getElementById("back-to-top") as HTMLElement).style.display = "none"
    }
  }


  // When the user clicks on the button, scroll to the top of the document
  topFunction() {
    document.body.scrollTop = 0;
    document.documentElement.scrollTop = 0;
  }


}
