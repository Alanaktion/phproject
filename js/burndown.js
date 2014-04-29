function oSize(obj) {
	var size = 0,
		key;
	for (key in obj) {
		if (obj.hasOwnProperty(key)) size++;
	}
	return size;
}

Burndown = {
	data: {
		labels: [],
		datasets: []
	},
	targetVelocity: {
		fillColor: "rgba(105,9,255,0.05)",
		strokeColor: "rgba(105,9,255,.5)",
		pointColor: "rgba(105,9,255,0)",
		pointStrokeColor: "rgba(105,9,255,0)",
		data: []
	},
	actualVelocity: {
		fillColor: "rgba(220,220,220,0.0)",
		strokeColor: "rgba(255,132,0,1)",
		pointColor: "rgba(118,82,43,1)",
		pointStrokeColor: "rgba(118,82,43,1)",
		data: []
	},
	hoursDay: {
		fillColor: "rgba(60,185,145,0.3)",
		strokeColor: "rgba(60,185,145,1)",
		pointColor: "rgba(220,220,220,1)",
		pointStrokeColor: "#326a58",
		data: []
	},
	days: 0,
	initialHours: 0,
	canvasId: "burndown",
	rawData: BurndownData[0],
	init: function() {
		Burndown.createData();
		Burndown.data.datasets.push(Burndown.actualVelocity);
		Burndown.data.datasets.push(Burndown.targetVelocity);
		Burndown.data.datasets.push(Burndown. hoursDay);
		var ctx = document.getElementById(Burndown.canvasId).getContext("2d");
		var burndown = new Chart(ctx).Line(Burndown.data, Burndown.options);
	},
	createData: function() {

		//set burndown days
		Burndown.days = oSize(Burndown.rawData);
		var i = 0;
		sum = 0;

		//strip down to initial daty and set initial hours
		$.each(Burndown.rawData, function(key, val) {
			if (i === 0) {
				Burndown.initialHours = Burndown.sumHours(this);
			} else {
				return;
			}
			i++;
		});

		var target = Burndown.initialHours,
			ratio = Burndown.initialHours / (Burndown.days - 1);
		i = Burndown.days; //tick days down for daily hours needed

		$.each(Burndown.rawData, function(key, val) {
			//set  labels for each day
			Burndown.data.labels.push(key);

			//set actual velocity
			if (val) { //check if it's null for actual velocity
				Burndown.actualVelocity.data.push(Burndown.sumHours(this));
				 Burndown. hoursDay.data.push(Burndown.sumHours(this) / i );
			}

			//set target data
			Burndown.targetVelocity.data.push(target);
			target = target - ratio;

			i--;

		});
	},
	sumHours: function(data) {
		var sum = 0;
		$.each(data, function(key1, val1) {
			if(!isNaN(parseFloat(this.remaining))){
				sum = parseFloat(this.remaining) + sum;
			}
		});
		return (sum);
	},
	options: {
		//Boolean - If we show the scale above the chart data
		scaleOverlay: false,

		//Boolean - If we want to override with a hard coded scale
		scaleOverride: false,

		//** Required if scaleOverride is true **
		//Number - The number of steps in a hard coded scale
		scaleSteps: 100,
		//Number - The value jump in the hard coded scale
		scaleStepWidth: null,
		//Number - The scale starting value
		scaleStartValue: 0,

		//String - Colour of the scale line
		scaleLineColor: "rgba(0,0,0,.1)",

		//Number - Pixel width of the scale line
		scaleLineWidth: 1,

		//Boolean - Whether to show labels on the scale
		scaleShowLabels: true,

		//Interpolated JS string - can access value
		scaleLabel: "<%=value%>",

		//String - Scale label font declaration for the scale label
		scaleFontFamily: "'Arial'",

		//Number - Scale label font size in pixels
		scaleFontSize: 12,

		//String - Scale label font weight style
		scaleFontStyle: "normal",

		//String - Scale label font colour
		scaleFontColor: "#666",

		///Boolean - Whether grid lines are shown across the chart
		scaleShowGridLines: true,

		//String - Colour of the grid lines
		scaleGridLineColor: "rgba(0,0,0,.05)",

		//Number - Width of the grid lines
		scaleGridLineWidth: 1,

		//Boolean - Whether the line is curved between points
		bezierCurve: false,

		//Boolean - Whether to show a dot for each point
		pointDot: true,

		//Number - Radius of each point dot in pixels
		pointDotRadius: 2,

		//Number - Pixel width of point dot stroke
		pointDotStrokeWidth: 1,

		//Boolean - Whether to show a stroke for datasets
		datasetStroke: true,

		//Number - Pixel width of dataset stroke
		datasetStrokeWidth: 2,

		//Boolean - Whether to fill the dataset with a colour
		datasetFill: true,

		//Boolean - Whether to animate the chart
		animation: true,

		//Number - Number of animation steps
		animationSteps: 60,

		//String - Animation easing effect
		animationEasing: "easeOutQuart",

		//Function - Fires when the animation is complete
		onAnimationComplete: null
	}
};
