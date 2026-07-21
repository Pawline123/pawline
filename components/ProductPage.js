'use client';
import { useEffect,useMemo,useState } from 'react';
import Link from 'next/link';
const money=n=>new Intl.NumberFormat('de-DE',{style:'currency',currency:'EUR'}).format(Number(n||0));
export default function ProductPage({handle}){
 const [product,setProduct]=useState(null),[variant,setVariant]=useState(null),[qty,setQty]=useState(1),[loading,setLoading]=useState(true);
 useEffect(()=>{fetch('/api/products').then(r=>r.json()).then(d=>{const p=(d.products||[]).find(x=>x.handle===handle);setProduct(p);setVariant(p?.variants?.[0]||null)}).finally(()=>setLoading(false));},[handle]);
 const image=useMemo(()=>product?.images?.[0]?.src,[product]);
 if(loading)return <div className="status pageStatus">Produkt wird geladen …</div>;
 if(!product)return <div className="status pageStatus">Produkt nicht gefunden. <Link href="/">Zurück zum Shop</Link></div>;
 const buy=()=>{if(!variant)return; window.location.href=`https://pawline-shop.de/cart/${variant.id}:${qty}`};
 return <><header><Link href="/" className="icon">←</Link><Link href="/" className="logo">PAWLINE<span>Für beste Freunde</span></Link><a className="icon" href="https://pawline-shop.de/cart">♡</a></header><main className="detail"><div className="detailImage">{image&&<img src={image} alt={product.title}/>}</div><div className="detailCopy"><p className="eyebrow">{product.product_type||product.vendor||'Pawline'}</p><h1>{product.title}</h1><div className="price">{money(variant?.price)}</div>{product.variants?.length>1&&<label>Variante<select value={variant?.id||''} onChange={e=>setVariant(product.variants.find(v=>String(v.id)===e.target.value))}>{product.variants.map(v=><option key={v.id} value={v.id}>{v.title} – {money(v.price)}</option>)}</select></label>}<label>Menge<input className="qty" type="number" min="1" max="20" value={qty} onChange={e=>setQty(Math.max(1,Number(e.target.value)))} /></label><button className="button full" onClick={buy} disabled={!variant?.available}> {variant?.available?'In den Warenkorb':'Derzeit nicht verfügbar'}</button><div className="description" dangerouslySetInnerHTML={{__html:product.body_html||'<p>Ein liebevoll ausgewähltes Produkt für deinen besten Freund.</p>'}}/><div className="trust">✓ Sicherer Shopify-Checkout<br/>✓ Verschlüsselte Zahlung<br/>✓ Versandinformationen im Checkout</div></div></main></>;
}
