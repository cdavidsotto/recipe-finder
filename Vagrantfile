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

    web.vm.provision "shell", inline: <<-SHELL
      set -e
      apt-get update
      apt-get install -y apache2
      systemctl enable --now apache2
    SHELL
  end
end
