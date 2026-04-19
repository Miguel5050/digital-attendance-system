const fs = require('fs');

let content = fs.readFileSync('c:/Users/migue/Desktop/digital-attendance-system/admin_dashboard.php', 'utf8');

let match = content.match(/<script>([\s\S]*?)<\/script>/);

if (match) {
    let js = match[1];
    js = js.replace(/<\?php[\s\S]*?\?>/g, '"dummy_php"');
    try { 
      new Function(js); 
      console.log('Syntax OK'); 
    } catch(e) { 
      console.log('Syntax Error: ' + e); 
      console.log(e.stack);
    }
}
