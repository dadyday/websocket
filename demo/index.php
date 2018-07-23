<html>
<body>
	<pre id="state"></pre>
	<button id="start" onclick="start();">Start</button>
	<button id="stop" onclick="stop();" disabled>Stop</button>

	<script>
		var conn;
		function connect() {
			conn = new WebSocket('ws://localhost:8001');
			conn.onopen = function(e) {
				console.log("Connection established!");
				document.getElementById('start').disabled = true;
				document.getElementById('stop').disabled = false;
			};

			conn.onerror = function (error) {
				console.log('WebSocket Error ' + error);
			};

			conn.onmessage = function(e) {
				console.log(e.data);
				document.getElementById('state').innerHTML += e.data+"\n";
				//conn.send('got '+e.data);
			};

			conn.onclose = function(e) {
				console.log("Connection closed!");
				document.getElementById('start').disabled = false;
				document.getElementById('stop').disabled = true;
			};
		};
		function start() {
			window.location.href = 'long-running.php';
			connect();
		};
		function stop() {
			console.log('stop');
			conn.send('stop');
		}
	</script>
</body>
</html>
