window.bootstrap = require('bootstrap');

const chartJs = require('chart.js');
const Chart = chartJs.Chart || chartJs;

if (chartJs.registerables) {
    Chart.register(...chartJs.registerables);
}

window.Chart = Chart;
