#!/usr/bin/python
#
# Copyright 2010 Red Hat, Inc. and/or its affiliates.
#
# Licensed to you under the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.  See the files README and
# LICENSE_GPL_v2 which accompany this distribution.
#

import thread, time, logging, struct, json
from threading import Event
from VirtIoChannel import VirtIoChannel

# Return a safe (password masked) repr of the credentials block.
def safe_creds_repr(creds):
    int_len = struct.calcsize('>I')
    user_len = struct.unpack('>I', creds[:int_len])[0]
    pass_len = len(creds) - user_len - int_len - 1
    cut = user_len + int_len
    return repr(creds[:cut] + ('*' * 8)+ creds[cut + pass_len:])

class DataRetriverBase:
    def getMachineName(self):
        pass
    def getOsVersion(self):
        pass
    def getAllNetworkInterfaces(self):
        pass
    def getApplications(self):
        pass
    def getAvailableRAM(self):
        pass
    def getUsers(self):
        pass
    def getActiveUser(self):
        pass
    def getDisksUsage(self):
        pass

class AgentLogicBase:

    def __init__(self, config):
        logging.debug("AgentLogicBase:: __init__() entered")
        self.wait_stop = Event()
        self.heartBitRate = config.getint("general", "heart_beat_rate")
        self.userCheckRate = config.getint("general", "report_user_rate")
        self.appRefreshRate = config.getint("general", "report_application_rate")
        self.disksRefreshRate = config.getint("general", "report_disk_usage")
        self.activeUser = ""
        self.vio = VirtIoChannel(config.get("virtio", "device"))
        self.dr = None
        self.commandHandler = None

    def run(self):
        logging.debug("AgentLogicBase:: run() entered")
        thread.start_new_thread(self.doListen, ())
        thread.start_new_thread(self.doWork, ())

        # Yuck! It's seem that Python block all signals when executing
        # a "real" code. So there is no way just to sit and wait (with
        # no timeout).
        # Try breaking out from this code snippet:
        # $ python -c "import threading; threading.Event().wait()"
        while not self.wait_stop.isSet():
            self.wait_stop.wait(1)

    def stop(self):
        logging.debug("AgentLogicBase:: baseStop() entered")
        self.wait_stop.set()

    def doWork(self):
        logging.debug("AgentLogicBase:: doWork() entered")
        self.sendInfo()
        self.sendUserInfo()
        self.sendAppList()
        counter = 0
        hbsecs = self.heartBitRate
        appsecs = self.appRefreshRate
        disksecs = self.disksRefreshRate
        usersecs = self.userCheckRate

        try:
            while not self.wait_stop.isSet():
                counter +=1
                hbsecs -= 1
                if hbsecs <= 0:
                    self.vio.write('heartbeat',  { 'free-ram' : self.dr.getAvailableRAM() })
                    hbsecs = self.heartBitRate
                usersecs -=1
                if usersecs <=0:
                    self.sendUserInfo()
                    usersecs = self.userCheckRate
                appsecs -= 1
                if appsecs <= 0:
                    self.sendAppList()
                    self.sendInfo()
                    appsecs = self.appRefreshRate
                disksecs -= 1
                if disksecs <= 0:
                    self.sendDisksUsages()
                    disksecs = self.disksRefreshRate
                time.sleep(1)
            logging.debug("AgentLogicBase:: doWork() exiting")
        except:
            logging.exception("AgentLogicBase::doWork")

    def doListen(self):
        logging.debug("AgentLogicBase::doListen() - entered")
        if self.commandHandler == None:
            logging.debug("AgentLogicBase::doListen() - no commandHandler ... exiting doListen thread")
            return
        while not self.wait_stop.isSet():
            try:
                logging.debug("AgentLogicBase::doListen() - in loop before vio.read")
                cmd, args = self.vio.read()
                logging.debug("AgentLogicBase::doListen() - read something")
		logging.debug(cmd)
		logging.debug(args)
                if cmd:
                    self.parseCommand(cmd, args)
            except:
                logging.exception('Error while reading the virtio-serial channel.')
        logging.debug("AgentLogicBase::doListen() - exiting")

    def parseCommand(self, command, args):
        logging.info("Received an external command: %s..." % (command))
        if command == 'lock-screen':
            self.commandHandler.lock_screen()
        elif command == 'log-off':
            self.commandHandler.logoff()
        elif command == 'shutdown':
            try:
                timeout = int(args['timeout'])
            except:
                timeout = 0
            try:
                msg = args['message']
            except:
                msg = 'System is going down'
            logging.info("Shutting down (timeout = %d, message = '%s')" % (timeout, msg))
            self.commandHandler.shutdown(timeout, msg)
        elif command == 'login':
            username = args['username'].encode('utf8')
            password = args['password'].encode('utf8')
            credentials = struct.pack('>I%ds%ds' % (len(username), len(password) + 1),
                len(username), username, password)
            logging.debug("User log-in (credentials = %s)" % (safe_creds_repr(credentials)))
            self.commandHandler.login(credentials)
        elif command == 'refresh':
            self.sendUserInfo(True)
            self.sendAppList()
            self.sendInfo()
            self.sendDisksUsages()
        elif command == 'echo':
            logging.debug("Echo: %s", args)
            self.vio.write('echo', args)
        elif command == 'etasp':
            method = args['method'].encode('utf8')        
            logging.debug("ETASP command: %s", method)
            res = self.commandHandler.etasp_read(method)
            if res != '':
                self.sendAppInfo('etasp', method, res, args) 
        else:
            logging.error("Unknown external command: %s (%s)" % (command, args))

    def sendUserInfo(self, force=False):
        cur_user = str(self.dr.getActiveUser())
        logging.debug("AgentLogicBase::sendUserInfo - cur_user = '%s'"%(cur_user))
        if cur_user != self.activeUser or force:
            self.vio.write('active-user', { 'name' : cur_user })
            self.activeUser = cur_user

    def sendInfo(self):
        self.vio.write('host-name', { 'name' : self.dr.getMachineName() })
        self.vio.write('os-version', { 'version' : self.dr.getOsVersion() })
        self.vio.write('network-interfaces', { 'interfaces' : self.dr.getAllNetworkInterfaces() })

    def sendAppList(self):
        self.vio.write('applications', { 'applications' : self.dr.getApplications() })

    def sendAppInfo(self, appname, method, msg, args):
        logging.debug(msg)
        obj = json.loads(msg.decode('utf8'))
        if method == 'getInstanceMetadata':
            obj['msg']['debug_mode'] = str(obj['msg']['debug_mode']);
        logging.debug("message")
        logging.debug(obj)
        self.vio.write(appname, { method : obj['msg'], 'id' : args['id'] })

    def sendDisksUsages(self):
        self.vio.write('disks-usage', { 'disks' : self.dr.getDisksUsage() })

    def sessionLogon(self):
        logging.debug("AgentLogicBase::sessionLogon: user logs on the system.")
        cur_user = self.dr.getActiveUser()
        retries = 0
        while (cur_user == 'None') and (retries < 5):
            time.sleep(1)
            cur_user = self.dr.getActiveUser()
            retries = retries + 1
        self.sendUserInfo()
        self.vio.write('session-logon')

    def sessionLogoff(self):
        logging.debug("AgentLogicBase::sessionLogoff: user logs off from the system.")
        self.activeUser = 'None'
        self.vio.write('session-logoff')
        self.vio.write('active-user', { 'name' : self.activeUser })

    def sessionLock(self):
        logging.debug("AgentLogicBase::sessionLock: user locks the workstation.")
        self.vio.write('session-lock')

    def sessionUnlock(self):
        logging.debug("AgentLogicBase::sessionUnlock: user unlocks the workstation.")
        self.vio.write('session-unlock')

    def sessionStartup(self):
        logging.debug("AgentLogicBase::sessionStartup: system starts up.")
        self.vio.write('session-startup')

    def sessionShutdown(self):
        logging.debug("AgentLogicBase::sessionShutdown: system shuts down.")
        self.vio.write('session-shutdown')