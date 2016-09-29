/* globals $ Chart */
var Burndown = {
	initialized: false,
	data: {
		labels: [BurndownLegendDict.hours_remaining],
		datasets: [{
			data: null,
			label: BurndownLegendDict.hours_remaining,
			borderColor: "#2ecc71",
			pointBackgroundColor: "#2ecc71",
			pointBorderColor: "#2ecc71",
		}]
	},
	options: {
		maintainAspectRatio: false,
		legend: {
			position: 'right'
		},
		animation: {
			duration: 250
		},
		hover: {
			mode: 'x-axis'
		},
		scales: {
			xAxes: [{
				type: "time",
				time: {
					min: BurndownRange.start,
					max: BurndownRange.end,
					// format: timeFormat,
					// round: 'day'
					// tooltipFormat: 'll HH:mm'
				},
				/*scaleLabel: {
					display: true,
					labelString: 'Date'
				}*/
			}, ],
			yAxes: [{
				ticks: {
					beginAtZero: true
				}
			}]
		},
		elements: {
			line: {
				tension: 0.3,
				borderWidth: 2,
			},
			point: {
				radius: 0,
				hoverRadius: 2,
			}
		}
	},
	init: function(canvasId, dataUrl) {
		Chart.defaults.global.defaultFontColor = "rgba(127,127,127,1)";
		Chart.defaults.scale.gridLines.color = "rgba(127,127,127,.3)";
		Chart.defaults.scale.gridLines.zeroLineColor = "rgba(127,127,127,.3)";
		this.initialized = true;

		$.get(dataUrl, function(data) {
			var finalData = [];
			$.each(data, function(key, val) {
				finalData.push({
					x: new Date(key),
					y: val
				});
			});

			Burndown.data.datasets[0].data = finalData;

			var ctx = document.getElementById(canvasId).getContext('2d');
			new Chart(ctx, {
				type: 'line',
				data: Burndown.data,
				options: Burndown.options
			});
		}, 'json');
	}
};
