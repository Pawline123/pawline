'use client';
import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';

const money = n => new Intl.NumberFormat('de-DE',{style:'currency',currency:'EUR'}).format(Number(n||0));
export default function Storefront(){
 const [products,setProducts]=useState([]),[query,setQuery]=useState(''),[category,setCategory]=useState('Alle'),[loading,setLoading]=useState(true),[menu,setMenu]=useState(false);
 useEffect(()=>{fetch('/api/products').then(r=>r.json()).then(d=>setProducts(d.products||[])).finally(()=>setLoading(false));},[]);
 const categories=useMemo(()=>['Alle',...Array.from(new Set(products.map(p=>p.product_type).filter(Boolean))).slice(0,8)], [products]);
 const filtered=products.filter(p=>(category==='Alle'||p.product_type===category)&&(`${p.title} ${p.vendor||''} ${p.tags||''}`).toLowerCase().includes(query.toLowerCase()));
 return <>
  <div className="topbar">Kostenloser Versand ab 49 € · Sicher bezahlen · Liebevoll ausgewählt</div>
  <header><button className="icon" onClick={()=>setMenu(!menu)} aria-label="Menü">☰</button><Link href="/" className="logo">PAWLINE<span>Für beste Freunde</span></Link><a className="icon" href="https://pawline-shop.de/cart" aria-label="Warenkorb">♡</a></header>
  {menu&&<nav className="drawer"><a href="#shop">Shop</a><a href="#versprechen">Unser Versprechen</a><a href="https://pawline-shop.de/pages/kontakt">Kontakt</a></nav>}
  <main>
   <section className="hero"><div className="heroText"><p className="eyebrow">DESIGN TRIFFT TIERLIEBE</p><h1>Schönes für ein glückliches Zuhause.</h1><p>Ausgewählte Lieblingsstücke für Hunde und Katzen – hochwertig, stilvoll und mit Liebe ausgesucht.</p><a href="#shop" className="button">Kollektion entdecken</a></div><div className="heroArt"><div className="petCard">🐶<span>Für Abenteurer</span></div><div className="petCard second">🐱<span>Für Genießer</span></div></div></section>
   <section id="versprechen" className="benefits"><div><b>Hochwertige Auswahl</b><span>Produkte, die zu euch passen.</span></div><div><b>Sicherer Checkout</b><span>Zahlung über deinen Shopify-Shop.</span></div><div><b>Schneller Versand</b><span>Direkt und zuverlässig.</span></div></section>
   <section id="shop" className="shop"><div className="sectionHead"><div><p className="eyebrow">PAWLINE KOLLEKTION</p><h2>Unsere Lieblinge</h2></div><input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Produkte suchen …" /></div>
   <div className="chips">{categories.map(c=><button key={c} className={category===c?'active':''} onClick={()=>setCategory(c)}>{c}</button>)}</div>
   {loading?<div className="status">Produkte werden geladen …</div>:filtered.length===0?<div className="status">Keine Produkte gefunden.</div>:<div className="grid">{filtered.map(p=>{const v=p.variants?.[0];const img=p.images?.[0]?.src;return <Link className="product" href={`/products/${p.handle}`} key={p.id}><div className="imageWrap">{img?<img src={img} alt={p.title}/>:<div className="placeholder">🐾</div>}<span className="badge">Liebling</span></div><div className="productInfo"><small>{p.product_type||p.vendor||'Pawline'}</small><h3>{p.title}</h3><p>{money(v?.price)}</p></div></Link>})}</div>}
   </section>
  </main>
  <footer><div className="logo light">PAWLINE<span>Für beste Freunde</span></div><div><a href="https://pawline-shop.de/policies/imprint">Impressum</a><a href="https://pawline-shop.de/policies/privacy-policy">Datenschutz</a><a href="https://pawline-shop.de/policies/terms-of-service">AGB</a><a href="https://pawline-shop.de/policies/refund-policy">Widerruf</a></div><p>© {new Date().getFullYear()} Pawline</p></footer>
 </>;
}
