const fs = require('fs');
const lines = fs.readFileSync('C:\\Users\\Excel Daud\\.gemini\\antigravity-ide\\brain\\52158935-b2c5-4a50-a288-aca941e1a9ae\\.system_generated\\logs\\transcript.jsonl', 'utf8').split('\n');
const recentLines = lines.slice(-500);
recentLines.forEach(line => {
    if (!line) return;
    try {
        const parsed = JSON.parse(line);
        if (parsed.source === 'MODEL' && parsed.content) {
            console.log("-------");
            console.log(parsed.content);
        }
    } catch(e) {}
});
