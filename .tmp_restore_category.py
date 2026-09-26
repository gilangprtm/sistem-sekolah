from pathlib import Path
import subprocess
p=Path('resources/js/pages/inventory/create.tsx'); s=p.read_text()
old=subprocess.check_output(['git','show','HEAD:resources/js/pages/inventory/create.tsx'],text=True)
needle='id="category-combobox"'
ci=old.index(needle)
start=old.rfind('                    <div',0,ci)
d=0;j=start
while j<len(old):
    if old.startswith('<div',j): d+=1
    elif old.startswith('</div>',j):
        d-=1
        if d==0: break
    j+=1
cat=old[start:j+6].replace('className="order-3 min-w-0 grid gap-2"','className="grid gap-2"')
# Current category block is absent; insert after name field before year.
needle2='''                            <div className="grid gap-2">
                                <Label htmlFor="tahun_pembelian">'''
pos=s.index(needle2)
s=s[:pos]+cat+'\n'+s[pos:]
p.write_text(s)
