Changes:
	background.js:
		added width and height for the popup
	
	popup.html
		added an event handler for the port received
		added a div#results
	
	includes/base.js
		sending a message to port1 with a document.title

-----------------------------------------------------------------------
Note:
	the message from the user script is sent via port1 - the meaning
	of port1 vs port2 is never explained. I assume, that port1 means
	"localPort" and port2 is "remotePort", i.e. the user script
	can only communicate with it's local, while it needs to send
	a different port to the remote listener.

-----------------------------------------------------------------------
Bug #1:
Steps to reproduce
	0. Assumes fresh install
	1. Open a tab [tab1] - load some page
	2. Open another tab [tab2] - load another page
	3. Switch to tab1
	4. Display popup

Problem:
	the title of the tab2 page displays

Reason:
	port variable in background.js got overwritten with a port to the 
	user script in tab2. it no longer communicates with a tab which is 
	focused, but rather communicates with a tab that is last opened

-----------------------------------------------------------------------  
Bug #2:
Steps to reproduce
	0. Assumes fresh install
	1. Open a page
	2. Display popup
	3. Click the popup again
	
Problem:
	An DOM exception when trying to "Respond to port".
	
Reason:
	Could be a bug in the browser, could be as designed, but as it
	seems - it's not possible to re-use the same MessageChannel
	to send multiple messages - only one round of communications
	is possible.
	More likely it's that the channel needs to be re-established
	every time a popup opens, since the old channel is destroyed
	when the old popup closes.
	There is no documentation about MessageChannel.

