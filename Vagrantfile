Vagrant.configure("2") do |config|
  config.vm.box = "bento/ubuntu-22.04"

  config.vm.define "webserver" do |web|
    web.vm.hostname = "webserver"

    web.vm.network "forwarded_port",
      guest: 80,
      host: 8080,
      host_ip: "127.0.0.1"

    web.vm.provider "virtualbox" do |vb|
      vb.memory = 1024
      vb.cpus = 1
    end

    web.vm.provision "shell", path: "build-webserver-vm.sh"
  end

  config.vm.define "appserver" do |app|
    app.vm.hostname = "appserver"
    app.vm.network "private_network", ip: "192.168.56.12"

    app.vm.provider "virtualbox" do |vb|
      vb.memory = 1024
      vb.cpus = 1
    end

    app.vm.provision "shell", path: "build-appserver-vm.sh"
  end

  config.vm.define "dbserver" do |db|
    db.vm.hostname = "dbserver"
    db.vm.network "private_network", ip: "192.168.56.13"

    db.vm.provider "virtualbox" do |vb|
      vb.memory = 1024
      vb.cpus = 1

    db.vm.provision "shell", path: "build-dbserver-vm.sh"
    end
  end
end
