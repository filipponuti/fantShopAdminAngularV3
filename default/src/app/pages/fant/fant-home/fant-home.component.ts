import { Component, OnInit } from '@angular/core';

interface HomeStat {
  label: string;
  value: string;
  icon: string;
  color: string;
  note: string;
}

@Component({
  selector: 'app-fant-home',
  templateUrl: './fant-home.component.html',
  styleUrls: ['./fant-home.component.scss'],
  standalone: false
})
export class FantHomeComponent implements OnInit {
  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];

  readonly stats: HomeStat[] = [
    { label: 'Vendite', value: '—', icon: 'ri-shopping-bag-3-line', color: 'primary', note: 'Dati in arrivo' },
    { label: 'Ordini', value: '—', icon: 'ri-file-list-3-line', color: 'success', note: 'Dati in arrivo' },
    { label: 'Clienti', value: '—', icon: 'ri-user-3-line', color: 'info', note: 'Dati in arrivo' },
    { label: 'Prodotti', value: '—', icon: 'ri-store-2-line', color: 'warning', note: 'Dati in arrivo' }
  ];

  ngOnInit(): void {
    this.breadCrumbItems = [{ label: 'FantShop' }, { label: 'Home', active: true }];
  }
}

