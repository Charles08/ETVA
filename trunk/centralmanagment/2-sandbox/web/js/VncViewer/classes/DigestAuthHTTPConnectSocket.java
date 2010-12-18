//
// DigestAuthHTTPConnectSocket.java implement as HTTPConnectSocket.java
// an alternate way to connect to VNC servers via HTTP proxies
// supporting the HTTP CONNECT method with Authentication
//

import java.net.*;
import java.io.*;

class DigestAuthHTTPConnectSocket extends Socket {

  public DigestAuthHTTPConnectSocket(String host, int port,
			   String proxyHost, int proxyPort,
                String username, String password)
    throws IOException {

    // Connect to the specified HTTP proxy
    //super();
    super(proxyHost, proxyPort);
    //Socket s = new Socket(proxyHost, proxyPort);

    //this.connect( new InetSocketAddress(proxyHost,proxyPort));
    // Send the CONNECT request and Authentication
    /*
    s.getOutputStream().write(("CONNECT " + host + ":" + port +
			     " HTTP/1.0"+
			     "\r\n\r\n").getBytes());
    */
    this.getOutputStream().write(("CONNECT " + host + ":" + port +
                     " HTTP/1.0" + "\r\n" +
                     "Proxy-Authorization: Basic " + 
                            Base64.base64Encode(
                                username + ":" + password
                            ) + 
                     "\r\n\r\n").getBytes());

    // Read the first line of the response
    DataInputStream is = new DataInputStream(this.getInputStream());
    String str = is.readLine();

    // Check the HTTP error code -- it should be "200" on success
    if (!str.startsWith("HTTP/1.0 200 ")) {
      if (str.startsWith("HTTP/1.0 "))
        str = str.substring(9);
      throw new IOException("Proxy reports \"" + str + "\"");
    }

    // Success -- skip remaining HTTP headers
    do {
      str = is.readLine();
    } while (str.length() != 0);
  }
}

