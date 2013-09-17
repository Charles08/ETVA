//
//  Copyright (C) 2002 Constantin Kaplinsky, Inc.  All Rights Reserved.
//
//  This is free software; you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation; either version 2 of the License, or
//  (at your option) any later version.
//
//  This software is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with this software; if not, write to the Free Software
//  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307,
//  USA.
//

//
// HTTPSConnectSSLSocketFactory.java together with HTTPSConnectSSLSocket.java
// implement an alternate way to connect to VNC servers via one or two
// HTTPS proxies supporting the HTTPS CONNECT method.
//

import javax.net.ssl.*;
import java.applet.*;
import java.net.*;
import java.io.*;

class HTTPSConnectSSLSocketFactory implements SocketFactory {

  public Socket createSocket(String host, int port, Applet applet)
    throws IOException {

    return createSocket(host, port,
			applet.getParameter("PROXYHOST1"),
			applet.getParameter("PROXYPORT1"));
  }

  public Socket createSocket(String host, int port, String[] args)
    throws IOException {

    return createSocket(host, port,
			readArg(args, "PROXYHOST1"),
			readArg(args, "PROXYPORT1"));
  }

  public Socket createSocket(String host, int port,
			     String proxyHost, String proxyPortStr)
    throws IOException {

    int proxyPort = 0;
    if (proxyPortStr != null) {
      try {
	proxyPort = Integer.parseInt(proxyPortStr);
      } catch (NumberFormatException e) { }
    }

    if (proxyHost == null || proxyPort == 0) {
      System.out.println("Incomplete parameter list for HTTPSConnectSSLSocket");
      SSLSocketFactory sf = (SSLSocketFactory) SSLSocketFactory.getDefault();
      return (SSLSocket) sf.createSocket(host,port);
    }

    System.out.println("HTTPS CONNECT via proxy " + proxyHost +
		       " port " + proxyPort);
    return HTTPSConnectSSLSocket.connectSSLSocket(host, port, proxyHost, proxyPort);
  }

  private String readArg(String[] args, String name) {

    for (int i = 0; i < args.length; i += 2) {
      if (args[i].equalsIgnoreCase(name)) {
	try {
	  return args[i+1];
	} catch (Exception e) {
	  return null;
	}
      }
    }
    return null;
  }
}
