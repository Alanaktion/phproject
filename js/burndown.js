function oSize(obj) {
	var size = 0,
		key;
	for (key in obj) {
		if (obj.hasOwnProperty(key)) size++;
	}
	return size;
}

Burndown = {
	chart: {},
	initialized: false,
	data: {
		labels: [],
		datasets: []
	},
	targetVelocity: {
		fillColor: "rgba(0,0,0,0)",
		strokeColor: "#9b59b6",
		pointColor: "#9b59b6",
		pointStrokeColor: "#9b59b6",
		pointHighlightFill: "#fff",
		pointHighlightStroke: "#9b59b6",
		data: []
	},
	actualVelocity: {
		fillColor: "rgba(42,204,113,0.1)",
		strokeColor: "#2ecc71",
		pointColor: "#2ecc71",
		pointStrokeColor: "#2ecc71",
		pointHighlightFill: "#fff",
		pointHighlightStroke: "#2ecc71",
		data: []
	},
	hoursDay: {
		fillColor: "rgba(52,152,219,0.1)",
		strokeColor: "#3498db",
		pointColor: "#3498db",
		pointStrokeColor: "#3498db",
		pointHighlightFill: "#fff",
		pointHighlightStroke: "#3498db",
		data: []
	},
	days: 0,
	initialHours: 0,
	canvasId: "burndown",
	init: function(data) {
		if(!data) {
			return false;
		}

		// Apply labels to datasets
		Burndown.actualVelocity.label = BurndownLegendDict.hours_remaining;
		Burndown.targetVelocity.label = BurndownLegendDict.target_velocity;
		Burndown.hoursDay.label = BurndownLegendDict.actual_velocity;

		// Populate datsets
		Burndown.createData(data);
		Burndown.data.datasets.push(Burndown.actualVelocity);
		Burndown.data.datasets.push(Burndown.targetVelocity);
		Burndown.data.datasets.push(Burndown.hoursDay);

		// Generate chart
		var ctx = document.getElementById(Burndown.canvasId).getContext("2d");
		Burndown.chart = new Chart(ctx).Line(Burndown.data, Burndown.options);

		Burndown.initialized = true;

	},
	createData: function(data) {
		if(!data) {
			return false;
		}

		// Set burndown days
		Burndown.days = oSize(data);
		var i = 0;
		sum = 0;

		// Strip down to initial daty and set initial hours
		$.each(data, function(key, val) {
			if (i === 0) {
				Burndown.initialHours = this.remaining;
			} else {
				return;
			}
			i++;
		});

		var target = Burndown.initialHours,
			ratio = Burndown.initialHours / (Burndown.days - 1);
		i = Burndown.days; // Tick days down for daily hours needed

		$.each(data, function(key, val) {
			// Set labels for each day
			Burndown.data.labels.push(key);

			// Set actual velocity
			if (val) { // Check if it's null for actual velocity
				Burndown.actualVelocity.data.push(this.remaining);
				Burndown.hoursDay.data.push(this.remaining / i);
			}

			// Set target data
			Burndown.targetVelocity.data.push(target);
			target = target - ratio;

			i--;

		});
	},
	options: {
		scaleBeginAtZero: true,
		bezierCurve: false,
		pointDot: true,
		pointDotRadius: 2,
		pointDotStrokeWidth: 1,
		datasetStroke: true,
		datasetStrokeWidth: 2,
		datasetFill: true,
		animation: false,
		responsive: true,
		maintainAspectRatio: false,
		scaleLineColor: "rgba(127,127,127,.2)",
		scaleGridLineColor : "rgba(127,127,127,.1)"
	}
};
