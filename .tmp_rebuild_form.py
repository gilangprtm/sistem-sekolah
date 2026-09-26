from pathlib import Path
p=Path('resources/js/pages/inventory/create.tsx')
s=p.read_text()
start=s.index('                <div className="grid min-w-0 gap-6 rounded-xl border p-6">')
end=s.index('\n                </div>\n            </div>\n        </AppLayout>', start)
region=s[start:end]
def block(marker):
    i=region.index(marker); d=0; j=i
    while j < len(region):
        if region.startswith('<div', j): d += 1
        elif region.startswith('</div>', j):
            d -= 1
            if d == 0: return region[i:j+6]
        j += 1
    raise RuntimeError(marker)
def fields_from(main):
    out=[]; pos=main.find('>')+1
    while True:
        i=main.find('                        <div className="grid gap-2">', pos)
        if i < 0: return out
        d=0; j=i
        while j < len(main):
            if main.startswith('<div',j): d += 1
            elif main.startswith('</div>',j):
                d -= 1
                if d == 0: break
            j += 1
        out.append(main[i:j+6]); pos=j+6
main=block('                    <div className="order-4 grid')
fields=fields_from(main)
by=lambda needle: next(x for x in fields if needle in x)
f=[by(x) for x in ['id="kode_barang"','id="nama_jenis_barang"','id="tahun_pembelian"','id="merk_type"','id="no_identitas"','id="bahan"','id="ukuran_konstruksi"','id="asal_perolehan"','id="satuan"','id="harga"','id="qty"']]
inv=block('                    <div className="order-1'); asset=block('                    <div className="order-2'); cat=block('                    <div className="grid gap-2">\n                        <Label htmlFor="category-combobox">'); notes=block('                    <div className="order-5'); actions=block('                    <div className="order-6')
inv=inv.replace('order-1 grid min-w-0 gap-2','grid min-w-0 gap-2').replace('''                        <p className="text-sm font-medium">
                            Klasifikasi Inventaris
                        </p>
''','')
asset=asset.replace('order-2 grid min-w-0 gap-4 md:grid-cols-2','grid min-w-0 gap-4')
cat=cat.replace('order-3 grid min-w-0 gap-2','grid min-w-0 gap-2')
cat=cat.replace('order-3 min-w-0 grid gap-2','grid min-w-0 gap-2')
notes_body=notes[notes.index('                        <Label'):notes.rindex('</div>')]
actions=actions.replace('order-6 flex flex-col-reverse justify-end gap-2 border-t pt-4 sm:flex-row','flex flex-col-reverse justify-end gap-2 border-t pt-4 sm:flex-row')
new='''                <div className="grid min-w-0 gap-6 rounded-xl border p-6">
                    <section className="grid min-w-0 gap-4 rounded-xl border p-5">
                        <div><h2 className="text-base font-semibold">Klasifikasi Inventaris</h2><p className="text-sm text-muted-foreground">Tentukan klasifikasi dan jenis aset.</p></div>
                        <div className="grid min-w-0 gap-4 md:grid-cols-3">'''+inv+asset+cat+'''</div>
                    </section>
                    <section className="grid min-w-0 gap-4 rounded-xl border p-5">
                        <div><h2 className="text-base font-semibold">Informasi Barang</h2><p className="text-sm text-muted-foreground">Lengkapi informasi detail barang.</p></div>
                        <div className="grid min-w-0 gap-4 md:grid-cols-3">'''+''.join(f[:8])+'''</div>
                    </section>
                    <section className="grid min-w-0 gap-4 rounded-xl border p-5">
                        <div><h2 className="text-base font-semibold">Nilai &amp; Jumlah</h2><p className="text-sm text-muted-foreground">Tentukan satuan, harga dan jumlah unit.</p></div>
                        <div className="grid min-w-0 gap-4 md:grid-cols-3">'''+''.join(f[8:])+'''</div>
                        <div className="grid min-w-0 gap-2"><p className="text-sm font-medium">Catatan Tambahan</p>'''+notes_body+'''</div>
                    </section>
'''+actions+'''\n                </div>'''
p.write_text(s[:start]+new+s[end:])
