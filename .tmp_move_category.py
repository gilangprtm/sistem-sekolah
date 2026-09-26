from pathlib import Path
p=Path('resources/js/pages/inventory/create.tsx')
s=p.read_text()
needle='                    <div className="grid gap-2">\n                        <Label htmlFor="category-combobox">'
start=s.index(needle)
d=0; j=start
while j < len(s):
    if s.startswith('<div',j): d+=1
    elif s.startswith('</div>',j):
        d-=1
        if d==0: break
    j+=1
cat=s[start:j+6]
s=s[:start]+s[j+6:]
# Insert category after the Nama/Jenis Barang field in Informasi card.
marker='                            <InputError message={errors.nama_jenis_barang} />\n                        </div>'
pos=s.index(marker)
pos += len(marker)
s=s[:pos]+'\n'+cat+s[pos:]
p.write_text(s)
