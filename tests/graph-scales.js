const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../admin/reports-graph.php'), 'utf8');
const script = [...source.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match => match[1]).find(code => code.includes('function serviceVisitAxis'));
assert.ok(script, 'Production chart script exists');
const labels = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
let checks = 0;
function render(days, hours) {
    const charts = [];
    const values = {days_labels_json: labels, days_data_json: days, hours_labels_json: ['08 AM','09 AM','10 AM'], hours_data_json: hours};
    const code = script.replace(/<\?php echo \$(\w+); \?>/g, (_, key) => JSON.stringify(values[key]));
    vm.runInNewContext(code, {document:{getElementById:id=>({getContext:()=>id})}, Chart:function(context, config){charts.push(config);}});
    return {charts, code};
}
for (const data of [[0,0,0,0,0], [0,1,0,0,0], [2,13,7,4,9], [0,106,250,12,0]]) {
    const hours = data.slice(0,3);
    const {charts} = render(data, hours);
    for (const [index, chart] of charts.entries()) {
        const expected = index ? hours : data;
        assert.deepEqual(JSON.parse(JSON.stringify(chart.data.datasets[0].data)), expected); checks++;
        assert.equal(chart.options.scales.y.beginAtZero, true); checks++;
        assert.ok(chart.options.scales.y.suggestedMax >= Math.max(5,...expected)); checks++;
        assert.equal(chart.options.scales.y.max, undefined); checks++;
        assert.equal(chart.options.scales.y.ticks.precision, 0); checks++;
        assert.equal(chart.options.scales.y.ticks.stepSize, undefined); checks++;
    }
}
const preview = render([2,13,7,4,9], [1,13,6]);
fs.writeFileSync(path.join(__dirname,'fixtures/graph-scales-preview.html'), `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Graph scale verification</title><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><style>body{font:16px Arial;background:#f5f7f8;margin:0;padding:24px}main{max-width:1000px;margin:auto}section{background:white;border-radius:12px;padding:20px;margin:20px 0}.chart{position:relative;height:330px}h1{font-size:24px}h2{font-size:20px}</style><main><h1>Weekday graph verification</h1><p>Synthetic data using the production chart configuration. Both charts include a count of 13.</p><section><h2>Weekly Traffic Volume</h2><div class="chart"><canvas id="daysChart"></canvas></div></section><section><h2>Peak Hours Analysis</h2><div class="chart"><canvas id="hoursChart"></canvas></div></section></main><script>${preview.code}</script></html>`);
console.log(`Passed ${checks} chart scale checks. Synthetic browser preview generated.`);
