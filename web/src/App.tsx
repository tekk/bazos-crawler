
import React, { useEffect, useState } from "react";
interface Ad {id:string;title:string;price:string;date:string;query:string;images:string[];htmlPath:string;description:string;contact:string;}
const App:React.FC=()=>{const[ads,setAds]=useState<Ad[]>([]);const[sel,setSel]=useState<Ad|null>(null);const[f,setF]=useState("all");
useEffect(()=>{fetch("./ads/index.json").then(r=>r.json()).then(d=>setAds(d.ads));},[]);
const queries=Array.from(new Set(ads.map(a=>a.query))).sort();
const list=f==="all"?ads:ads.filter(a=>a.query===f);
return(<div className="min-h-screen bg-neutral-900 text-neutral-100 p-4">
<header className="flex gap-4 mb-6"><h1 className="text-2xl font-bold">Bazoš Viewer</h1>
<select value={f} onChange={e=>{setF(e.target.value);setSel(null)}} className="bg-neutral-800 px-2 rounded"><option value="all">všetko</option>
{queries.map(q=><option key={q}>{q}</option>)}</select></header>
<div className="grid md:grid-cols-3 gap-6">
<div className="space-y-2 md:max-h-[80vh] overflow-y-auto">{list.map(a=><div key={a.id} onClick={()=>setSel(a)} className={"p-3 border rounded "+(sel?.id===a.id?"bg-neutral-800":"")}>
<div className="font-semibold">{a.title}</div><div className="text-sm text-neutral-400 flex justify-between"><span>{a.price}</span><span>{a.date}</span></div></div>)}</div>
<div className="md:col-span-2 border rounded p-4 min-h-[400px]">{sel?
<> <h2 className="text-xl font-bold mb-2">{sel.title}</h2>
<div className="text-sm mb-2 text-neutral-400">{sel.price} • {sel.date} • {sel.query}</div>
{sel.images.length? <div className="grid md:grid-cols-2 gap-2 mb-4">{sel.images.map(i=><img key={i} src={i} />)}</div>:null}
<p className="whitespace-pre-wrap mb-4">{sel.description}</p>{sel.contact&&<p>{sel.contact}</p>}
<a href={sel.htmlPath} target="_blank" className="underline">Otvoriť uložené HTML</a></>:
<p className="text-neutral-400">Vyber inzerát…</p>}</div></div></div>);
};
export default App;
