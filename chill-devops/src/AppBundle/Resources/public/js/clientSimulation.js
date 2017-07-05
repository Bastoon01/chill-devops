/**
 * Created by devmercerie on 15/06/17.
 */


var socket = io.connect('http://192.170.1.14:9090');

function getWaiting(){
    socket.emit('waiting');
};

socket.on('waiting', function(waiting){
    console.log(waiting);
});

socket.on('response', function(results) {
    console.log(results);
});