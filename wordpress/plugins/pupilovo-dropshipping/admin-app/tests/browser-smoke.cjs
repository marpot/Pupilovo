const { chromium } = require('playwright');
const fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'../../tests');
const fixture=JSON.parse(fs.readFileSync(path.join(root,'browser-fixture.local.json'),'utf8'));
(async()=>{
 const browser=await chromium.launch({headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 const failures=[];
 page.on('pageerror',e=>failures.push(e.message));
 try{
 await page.goto('http://localhost:8080/wp-login.php');
 await page.locator('#user_login').fill(fixture.user);
 await page.locator('#user_pass').fill(fixture.pass);
 await page.locator('#wp-submit').click();
 await page.waitForURL(/wp-admin/);
 console.log('PASS login temporary admin');
 await page.goto('http://localhost:8080/wp-admin/admin.php?page=pupilovo-supplier-hub');
 await page.getByRole('heading',{name:'Dashboard',exact:true}).waitFor();
 console.log('PASS dashboard rendered');
 for(const [hash,title] of [['suppliers','Hurtownie'],['catalog','Katalog produktów'],['categories','Kategorie'],['selected','Wybrane produkty'],['import','Import'],['pricing','Ceny i marże'],['history','Historia i błędy']]){
 await page.goto('http://localhost:8080/wp-admin/admin.php?page=pupilovo-supplier-hub#/'+hash);
 await page.getByRole('heading',{name:title,exact:true,level:1}).waitFor();
 console.log('PASS route '+hash);
 }
 await page.goto('http://localhost:8080/wp-admin/admin.php?page=pupilovo-supplier-hub#/suppliers');
 await page.getByRole('button',{name:/Dodaj hurtownię/}).click();
 await page.getByLabel('Nazwa hurtowni').fill('Browser QA '+fixture.user);
 await page.getByRole('button',{name:'Zapisz jako szkic'}).click();
 await page.getByText('Browser QA '+fixture.user).first().waitFor();
 console.log('PASS create supplier form');
 const row=page.locator('tr').filter({hasText:'Browser QA '+fixture.user}).first();
 await row.getByRole('button',{name:'Konfiguruj'}).click();
 await page.getByRole('button',{name:'Zapisz i przejdź dalej'}).click();
 await page.getByRole('button',{name:'Sprawdź źródło'}).waitFor();
 await page.locator('input[type=file]').setInputFiles(path.join(root,'fixtures/demo-pet-supplier.xml'));
 await page.getByRole('button',{name:'Sprawdź źródło'}).click();
 await page.getByText(/Wykryto .* pól/).waitFor({timeout:15000});
 console.log('PASS upload XML and inspect fields');
 await page.screenshot({path:path.join(__dirname,'supplier-wizard-smoke.png'),fullPage:true});
 } finally {
 console.log('BROWSER_PAGE_ERRORS',failures.length,JSON.stringify(failures));
 await browser.close();
 }
})().catch(e=>{console.error('FAIL',e.message);process.exitCode=1;});
