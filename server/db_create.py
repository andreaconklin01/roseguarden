__author__ = 'drobisch'

from app.server import db, flask_bcrypt
from app.models import User, Door, Request, Setting
import datetime
import base64

db.create_all()
User.query.delete()


db.session.add(User('m.drobisch@googlemail.com','1234','Marcus','Drobisch',1,'01754404298',0x00,0x03))
db.session.add(User('m.mustermann@googlemail.com','1234','Max','Mustermann'))

#db.session.add(User(id = 0, password = flask_bcrypt.generate_password_hash('1234'), token = base64.encodestring('m.drobisch@googlemail.com:1234'), tokenExpirationDate= datetime.datetime.utcnow(), firstName = 'Marcus', lastName = 'Drobisch', phone = '0175 4404298', email='m.drobisch@googlemail.com', card_id = '1.1.1.1' , doorLicense = 0x01, deviceLicense = 0x01))
#db.session.add(User(id = 1, password = flask_bcrypt.generate_password_hash('1234'), token = base64.encodestring('m.mustermann@googlemail.com:1234'), tokenExpirationDate= datetime.datetime.utcnow(), firstName = 'Max', lastName = 'Mustermann', phone = '0175 4404298', email='m.mustermann@googlemail.com', card_id = '2.1.1.1'  , doorLicense = 0x00, deviceLicense = 0x00))

Door.query.delete()
db.session.add(Door(id = 0, name = 'front door', address = 'http://localhost', keyMask = 0x01, local = 0x01 ))
db.session.add(Door(id = 1, name = 'inner door', address = 'http://localhost', keyMask = 0x02, local = 0x00 ))

Request.query.delete()
db.session.add(Request('Marcus Drobisch','m.drobisch@googlemail.com', '1.1.1.1','Front door opening','Door',datetime.datetime.now()))
db.session.add(Request('Max Mustermann','m.mustermann@googlemail.com', '1.1.1.1','Front door opening','Door',datetime.datetime.now()))

Request.query.delete()
db.session.add(Setting('localDoorName','Test door','TEXT'))
db.session.add(Setting('localDoorKey','0x03','KEYSLOT'))
db.session.commit()