const {chromium}=require('playwright'),fs=require('fs'),path=require('path');
const fixture=JSON.parse(fs.readFileSync(path.resolve(__dirname,'../../tests/browser-fixture.local.json'),'utf8'));
(async()=>{
 const browser=await chromium.launch({headless:true});const page=await browser.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const supplier={id:99991,name:'QA Demo',slug:'qa-demo',sourceType:'file_xml',status:'draft',lastImportAt:null};
 let selected=false,categoryMapped=false,approved=0,previewCalls=0;
 const product={id:99992,name:'Testowa miska',externalId:'QA-1',sku:'QA-SKU-1',ean:'',supplierName:'QA Demo',purchasePrice:12.5,currency:'PLN',stockQuantity:5,availability:'available',supplierCategoryPath:'Miski',wcProductId:null,primaryImageUrl:null};
 await page.route('**/wp-json/pupilovo-supplier-hub/v1/**',async route=>{
 const u=new URL(route.request().url()),p=u.pathname,method=route.request().method();
 const respond=(body)=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)});
 if(p.endsWith('/suppliers'))return respond({items:[supplier],total:1,page:1,totalPages:1});
 if(p.endsWith('/catalog'))return respond({items:[{...product,selected}],total:1,page:1,totalPages:1});
 if(p.endsWith('/selections')&&method==='PUT'){selected=JSON.parse(route.request().postData()).selected;return respond({success:true});}
 if(p.endsWith('/categories')&&!p.endsWith('/woocommerce/categories'))return respond({items:[{id:99993,name:'Miski',path:'Miski',wcTermId:categoryMapped?10:null,decision:categoryMapped?'approved':'pending'}]});
 if(p.endsWith('/woocommerce/categories'))return respond({items:[{id:10,name:'Miski',path:'Miski'}]});
 if(p.endsWith('/category-mappings')&&method==='POST'){categoryMapped=true;return respond({success:true});}
 if(p.endsWith('/import-preview')&&method==='POST'){
 previewCalls++;return respond({job:{id:99994,type:'import',status:'preview',totalItems:1,processedItems:0,succeededItems:0,failedItems:0,createdAt:'2026-10-08',context:{summary:categoryMapped?{create:1,update:0,conflict:0,skip:0}:{create:0,update:0,conflict:1,skip:0}}},items:[{id:99995,action:categoryMapped?'create':'conflict',status:categoryMapped?'pending':'blocked',errorCode:categoryMapped?null:'category_unmapped',after:{product:{name:'Testowa miska'},pricing:{salePriceGross:18,currency:'PLN'},errors:categoryMapped?[]:['Kategoria nieprzypisana']}}]});
 }
 if(p.endsWith('/jobs/99994/approve')&&method==='POST'){approved++;return respond({success:true});}
 return route.continue();
 });
 const url='http://localhost:8080/wp-admin/admin.php?page=pupilovo-supplier-hub';
 try{
 await page.goto('http://localhost:8080/wp-login.php');await page.locator('#user_login').fill(fixture.user);await page.locator('#user_pass').fill(fixture.pass);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 await page.goto(url+'#/catalog');await page.getByRole('checkbox',{name:'Wybierz Testowa miska'}).click();await page.waitForTimeout(350);if(!selected)throw Error('selection not saved');console.log('PASS product selection');
 await page.goto(url+'#/selected');await page.getByText('Testowa miska').first().waitFor();console.log('PASS selected products visible');
 await page.goto(url+'#/import');await page.getByRole('button',{name:'Sprawdź plan przed importem'}).click();await page.getByRole('alert').filter({hasText:'Import zablokowany'}).waitFor();await page.getByRole('checkbox',{name:/Potwierdzam plan/}).check();
 if(await page.getByRole('button',{name:'Zatwierdź i dodaj do kolejki'}).isEnabled())throw Error('blocked plan approved');console.log('PASS conflicts block approval');
 await page.goto(url+'#/categories');await page.getByRole('combobox',{name:'Mapowanie Miski'}).selectOption('10');if(!categoryMapped)throw Error('mapping not saved');console.log('PASS category mapping');
 await page.goto(url+'#/import');await page.getByRole('button',{name:'Sprawdź plan przed importem'}).click();await page.getByRole('checkbox',{name:/Potwierdzam plan/}).check();await page.getByRole('button',{name:'Zatwierdź i dodaj do kolejki'}).click();
 if(approved!==1)throw Error('approval missing');console.log('PASS valid plan approved once');
 console.log('PASS preview requests',previewCalls,'JS errors',errors.length);if(errors.length)throw Error(errors.join('; '));
 }finally{await browser.close();}
})().catch(e=>{console.error('FAIL',e.message);process.exitCode=1});
