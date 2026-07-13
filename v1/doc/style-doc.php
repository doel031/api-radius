<?php
// api/v1/doc/style-doc.php
header("Content-Type: text/css");
?>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { 
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
    line-height: 1.6; color: #2d3748; background-color: #f7fafc; padding: 30px;
}
.container { max-width: 900px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
h1 { font-size: 26px; color: #1a202c; border-bottom: 3px solid #3182ce; padding-bottom: 12px; margin-bottom: 20px; }
h2 { color: #2b6cb0; margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; font-size: 20px; }
h3 { color: #2d3748; margin-top: 25px; margin-bottom: 10px; font-size: 15px; background: #e2e8f0; padding: 6px 12px; border-radius: 6px; font-weight: 600; }
p { margin-bottom: 15px; font-size: 15px; color: #4a5568; }
a.back-btn { display: inline-block; margin-bottom: 20px; color: #3182ce; text-decoration: none; font-weight: bold; font-size: 14px; }
a.back-btn:hover { text-decoration: underline; }
.endpoint-box { background: #f7fafc; border-left: 5px solid #48bb78; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; border-left-width: 5px; margin: 20px 0; display: flex; align-items: center; gap: 10px; }
.endpoint-box.blue { border-left-color: #3182ce; }
.method { background: #48bb78; color: #fff; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; }
.method.blue { background: #3182ce; }
.url { font-family: monospace; font-weight: bold; color: #1a202c; font-size: 14px; }
.table-container { width: 100%; overflow-x: auto; margin: 20px 0; border: 1px solid #e2e8f0; border-radius: 8px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 15px; text-align: left; font-size: 14px; border-bottom: 1px solid #e2e8f0; }
th { background: #edf2f7; color: #4a5568; }
.badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; display: inline-block; }
.badge-req { background: #fed7d7; color: #9b2c2c; }
.badge-opt { background: #feebc8; color: #9c4221; }
pre { background: #1a202c; color: #f7fafc; padding: 16px; border-radius: 8px; overflow-x: auto; font-family: monospace; font-size: 13px; margin: 15px 0; }
.explanation-box { background: #f7fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; margin-top: -10px; margin-bottom: 25px; font-size: 14px; }
