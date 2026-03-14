const fs = require("fs");
const s = fs.readFileSync("c:/xampp/htdocs/sap-computers/assets/js/app.js","utf8");
const count=(c)=>s.split(c).length-1;
console.log("backticks",count("`") );
console.log("single quotes",count("'"));
console.log("double quotes",count('"'));
