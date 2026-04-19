const fs = require('fs');
let content = fs.readFileSync('c:/Users/migue/Desktop/digital-attendance-system/admin_dashboard.php', 'utf8');

// Replace exactly \` with `
content = content.replace(/\\`/g, '`');

// Replace exactly \$ with $
content = content.replace(/\\\$/g, '$');

// Re-write
fs.writeFileSync('c:/Users/migue/Desktop/digital-attendance-system/admin_dashboard.php', content);
console.log("Cleaned up backslashes.");
