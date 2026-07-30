import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-fant-cataloghi',
  templateUrl: './fant-cataloghi.component.html',
  styleUrls: ['./fant-cataloghi.component.scss'],
  standalone: false
})
export class FantCataloghiComponent implements OnInit {
  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];

  ngOnInit(): void {
    this.breadCrumbItems = [
      { label: 'Shop' },
      { label: 'Cataloghi', active: true }
    ];
  }
}
