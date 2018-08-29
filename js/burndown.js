/* globals $ Chart BurndownLegendDict BurndownRange */
var Burndown = {
	initialized: false,
	chart: null,
	data: {
		datasets: [{
			data: null,
			label: BurndownLegendDict.hours_remaining,
			borderColor: '#2ecc71',
			pointBackgroundColor: '#2ecc71',
			pointBorderColor: '#2ecc71',
		}, {
			data: null,
			label: BurndownLegendDict.man_hours_remaining,
			borderColor: '#9b59b6',
			pointBackgroundColor: '#9b59b6',
			pointBorderColor: '#9b59b6',
		}]
	},
	options: {
		maintainAspectRatio: false,
		legend: {
			position: 'bottom',
			labels: {
				boxWidth: 1
			}
		},
		animation: {
			duration: 250
		},
		tooltips: {
			mode: 'x-axis'
		},
		hover: {
			mode: 'x-axis'
		},
		scales: {
			xAxes: [{
				type: 'time',
				time: {
					min: BurndownRange.start,
					max: BurndownRange.end,
					minUnit: 'day',
					tooltipFormat: 'ddd MMM D, h A',
					displayFormats: {
						day: 'ddd MMM D',
					},
				},
			}],
			yAxes: [{
				ticks: {
					beginAtZero: true
				}
			}]
		},
		elements: {
			line: {
				tension: 0.05,
				borderWidth: 2,
			},
			point: {
				radius: 0,
				hitRadius: 5,
				hoverRadius: 3,
			}
		}
	},
	init: function(canvasId, dataUrl) {
		Chart.defaults.global.defaultFontColor = 'rgba(127,127,127,1)';
		Chart.defaults.scale.gridLines.color = 'rgba(127,127,127,.3)';
		Chart.defaults.scale.gridLines.zeroLineColor = 'rgba(127,127,127,.3)';
		this.initialized = true;

		$.get(dataUrl, function(data) {
			var finalData = [];
			$.each(data, function(key, val) {
				finalData.push({
					x: key,
					y: val
				});
			});

			Burndown.data.datasets[0].data = finalData;
			Burndown.data.datasets[1].data = [
				{x: BurndownRange.start, y: BurndownManHours || 0},
				{x: BurndownRange.end, y: 0}
			];

			$('#' + canvasId).parents('.modal-body').removeAttr('data-loading');

			var ctx = document.getElementById(canvasId).getContext('2d');
			Burndown.chart = new Chart(ctx, {
				type: 'line',
				data: Burndown.data,
				options: Burndown.options
			});
		}, 'json').fail(function() {
			$('#' + canvasId).parents('.modal-body').removeAttr('data-loading')
				.html('<p class="alert alert-danger">Failed to load burndown data!</p>');
		});
	}
};
